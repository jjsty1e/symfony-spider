<?php

/**
 * Created by PhpStorm.
 * User: Jaggle
 * Date: 2017-05-05
 * Time: 10:04
 */

namespace AppBundle\Command;

use AppBundle\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Registry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use QL\QueryList;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 启动一个任务
 *
 * 作用：从redis中得到一个jobId,并且爬取这个job，如果爬取成功，将得到的数据存入到redis中（只后由任务队列执行入库操作）
 *
 * 每个任务会检测当前是否有job在获取category页面，如果没有，那么当前这个job会当作category-fetcher
 *
 * Class JobRunCommand
 * @package AppBundle\Command
 */
class JobRunCommand extends ContainerAwareCommand
{
    /**
     * @var SymfonyStyle $io
     */
    private $io;

    /**
     * @var int 当前的爬虫id
     */
    private $spiderName;
    
    protected function configure()
    {
        $this->setName('job:run');
        $this->addArgument('spiderName');
    }
    
    /**
     * @return Registry
     */
    protected function getDoctrine()
    {
        return $this->getContainer()->get('doctrine');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = $io = new SymfonyStyle($input, $output);

        $this->spiderName = $input->getArgument('spiderName');
        $spiderService      = $this->getContainer()->get('app.spider.service');
        $jobRepository      = $this->getDoctrine()->getRepository('AppBundle:Job');
        $documentRepository = $this->getDoctrine()->getRepository('AppBundle:Document');
        $redis              = $this->getContainer()->get('snc_redis.cache');
        $spiderRepository = $this->getDoctrine()->getRepository('AppBundle:Spider');
        
        $spider = $spiderRepository->findOneBy(['name' => $this->spiderName]);
        $spiderId = $spider->getId();

        while (true) {
            if ($redis->scard('spider:waiting-job') == 0) {
                $spiderService->createWaitingJobSet($this->spiderName);
            }

            $io->note('[JOB] - created new job');

            if (!$spiderId) {
                throw new InvalidArgumentException('argument:spiderId error!');
            }


            do {
                $jobId = $redis->spop('spider:waiting-job');
                
                if (!$jobId) {
                    $io->warning('No waiting job, sleep 5 seconds then check again!');
                    sleep(5);
                    continue;
                }
                
                $isRunning = $redis->sismember('spider:running-job', $jobId);
                
                if ($isRunning) {
                    $io->warning('Job:'. $jobId .' is Running, go get next one!');
                    sleep(5);
                    continue;
                } else {
                    break;
                }
                
            } while (true);

            $redisRet = $redis->sadd('spider:running-job', [$jobId]);

            /**
             * 在redis中保存当前job的状态，以避免其他进程重复获取这个id
             */

            if (!$redisRet) {
                throw new \Exception(sprintf('[JOB] - job:%s is running', $jobId));
            }

            $job = $jobRepository->find($jobId);

            if (!$job) {
                throw new \Exception('none exist job: ' . $jobId);
            }

            $jobRepository->updateJobStatus($job, 1);

            /**
             * 任务已经有了对应的文档, 避免并发异常
             */
            if ($documentRepository->getDocumentByLink($job->getLink())) {
                $spiderService->finishJob($jobId);
                return;
            }

            list($links, $documentResource) = $this->crawl($job);

            // push link job to redis queue
            if ($links) {
                $validLinks = array_filter($links, function ($value) use ($jobRepository) {
                    return strlen($value) < 255 && !(bool) $jobRepository->findOneBy(['link' => $value]);
                });

                $redisJobs = array_map(function ($value) use ($spiderId){
                    return json_encode([
                        'spiderId' => $spiderId,
                        'link' => $value
                    ]);
                }, $validLinks);

                $spiderService->pushRedisJob($redisJobs);
            }

            // push document to redis queue
            if ($documentResource) {
                $document = $documentRepository->findOneBy(['title' => $documentResource['title']]);

                /**
                 * 已经存在相同的文档
                 */
                if ($document) {
                    $spiderService->finishJob($jobId);
                }

                $this->io->success(sprintf('[JOB] - Got new document on this page:%s', $job->getLink()));

                $spiderService->pushRedisDocument($documentResource);
            }
        }
    }
    
    /**
     * 爬取得到所有的站内链接
     *
     * @param Job $job
     * @return array
     * @throws \Exception
     */
    protected function crawl(Job $job)
    {
        $spiderRepository = $this->getDoctrine()->getRepository('AppBundle:Spider');
        $spider = $spiderRepository->find($job->getSpiderId());
        $spiderService = $this->getContainer()->get('app.spider.service');
        
        if ($job->getStatus() !== 1) {
            throw new \Exception('Job:' . $job->getId() . ' is not running');
        }

        $this->io->note('now crawl link: ' . $job->getLink());
        
        $linkRule = [
            'link' => ['a', 'href']
        ];
        
        $rules = $spiderService->getRules($spider->getName());
    
        $documentRule = [];
        
        foreach ($rules['documentRule'] as $ruleName => $rule) {
            $documentRule[$ruleName] = [
                $rule['rule'],
                $rule['type']
            ];
        }
        
        $client = new Client();

        try {
            $response = $client->get($this->parseJobLink($job), [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.98 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Referer' => $spider->getSite()
                ]
            ]);
        } catch (ClientException $exception) {
            $this->io->error($exception->getMessage());
            return [null, null];
        }

        $contentHtml = $response->getBody()->getContents();
        
        /**
         * @var QueryList $ql
         */
        $ql = QueryList::Query($contentHtml, $linkRule);

        $data = $ql->getData();

        $links = [];

        foreach ($data as $item) {
            $link = trim($item['link']);

            if (strlen($link) > 255) {
                continue;
            }

            // 合格的链接
            if ($link) {
                if (preg_match("#^http(s)?://.*?{$spider->getDomain()}#", $link)) {
                    $links[] = $link;
                }

                if (strpos($link, '/') === 0) {
                    $urlData = parse_url($spider->getSite());
                    $links[] = sprintf('%s://%s%s', $urlData['scheme'], $urlData['host'], $link);
                }
            }
        }
        
        $ql = QueryList::Query($contentHtml, $documentRule);
        
        $originDoc = $ql->getData();
        
        if (isset($originDoc[0]) && !empty($originDoc[0]['title']) && !empty($originDoc[0]['content'])) {
            $document = $originDoc[0];

            if (empty($document['meta'])) {
                $document['meta'] = '';
            }

            if (empty($document['desc'])) {
                $document['desc'] = '';
            }
            
            $document['jobId'] = $job->getId();
            $document['link'] = $job->getLink();
            $document['spiderId'] = $job->getSpiderId();
        } else {
            $this->io->note(sprintf('[JOB] - no document on this page: %s', $job->getLink()));
            return [$links, null];
        }
        
        return [$links, $document];
    }
    
    
    /**
     * 处理链接
     *
     * @param Job $job
     * @return mixed|string
     */
    protected function parseJobLink(Job $job)
    {
        return $job->getLink();
    }
}
