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
use Doctrine\ORM\EntityManager;
use GuzzleHttp\Client;
use QL\QueryList;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 启动一个任务
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
    
    protected function configure()
    {
        $this->setName('job:run');
        $this->addArgument('spiderId');
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
        
        //$io->note('[JOB] - created new job');
        
        $spiderId = $input->getArgument('spiderId');
        
        if (!$spiderId) {
            throw new InvalidArgumentException('argument:spiderId error!');
        }
        
        $jobRepository = $this->getDoctrine()->getRepository('AppBundle:Job');
        $documentRepository = $this->getDoctrine()->getRepository('AppBundle:Document');
        
        /**
         * @var EntityManager $entityManager
         */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            $entityManager->beginTransaction();

            $job = $jobRepository->getOneUnProcessJobWithLock($spiderId, 'bang/info');
    
            if (!$job) {
                throw new \Exception('no more unProcess job');
            }
    
            /**
             * 任务已经有了对应的文档
             */
            if ($documentRepository->getDocumentByJobId($job->getId())) {
                $jobRepository->finishJob($job);
                
                $entityManager->commit();
                return;
            }
    
            $job->setStatus(1);
            $job->setRetry($job->getRetry() + 1);
            $job->setUpdateTime(new \DateTime());
            
            $entityManager->flush();
            
            $entityManager->commit(); // 这些数据不需要事务
            
            $entityManager->beginTransaction();
            
            list($links, $documentResource) = $this->crawl($job);
            
            foreach ($links as $link) {
                if (!$jobRepository->findOneBy(['link' => $link])) {
                    $jobRepository->createJob($spiderId, $link);
                }
            }
            
            if ($documentResource) {
                $document = $documentRepository->findOneBy(['title' => $documentResource['title']]);
    
                /**
                 * 已经存在相同的文档
                 */
                if ($document) {
                    $jobRepository->finishJob($job);
    
                    $entityManager->commit();
                    return;
                }
    
                $this->io->success(sprintf('[JOB] - Got new document on this page:%s', $job->getLink()));
                
                $title = $documentResource['title'];
                $content = $documentResource['content'];
                $meta = $documentResource['meta'];
                $desc = $documentResource['desc'];
                
                $documentRepository->createDocument($title, $job->getId(), $meta, $job->getLink(), $content, $desc);
    
                $jobRepository->finishJob($job);
            }
    
            $entityManager->commit();
            
        } catch (\Exception $exception) {
            $entityManager->rollback();
            throw $exception;
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
        $jobRepository = $this->getDoctrine()->getRepository('AppBundle:Job');
        
        $spider = $spiderRepository->find($job->getSpiderId());
        
        if ($job->getStatus() !== 1) {
            throw new \Exception('Job:' . $job->getId() . ' is not running');
        }
        
        $linkRule = [
            'link' => ['a', 'href']
        ];
        
        $documentRule = [
            'title' => ['h1', 'text'],
            'date' => ['.f-l.article-tips', 'text'],
            'desc' => ['.short-article', 'text'],
            'meta' => ['.f-l.article-tips', 'text'],
            'content' => ['.main-article.clr', 'html']
        ];
        
        $client = new Client();
        
        $response = $client->get($job->getLink());
        $contentHtml = $response->getBody()->getContents();
        
        /**
         * @var QueryList $ql
         */
        $ql = QueryList::Query($contentHtml, $linkRule);

        $data = $ql->getData();

        $links = [];

        foreach ($data as $item) {
            $link = trim($item['link']);

            // 合格的链接
            if ($link) {
                if (preg_match("#^http(s)?://.*?{$spider->getDomain()}#", $link)) {
                    $links[] = $link;
                }

                if (strpos($link, '/') === 0) {
                    $links[] = sprintf('%s%s', $spider->getSite(), $link);
                }
            }
        }
        
        $ql = QueryList::Query($contentHtml, $documentRule);
        
        $originDoc = $ql->getData();
        
        if (isset($originDoc[0]) && !empty($originDoc[0]['title']) && !empty($originDoc[0]['content'])) {
            $document = $originDoc[0];
        } else {
            $this->io->note(sprintf('[JOB] - no document on this page:%s', $job->getLink()));
            echo $contentHtml;
            throw new NotFoundHttpException('no document, exit');
        }
        
        if ($response->getStatusCode() == 200) {
            $jobRepository->finishJob($job);
        }
        
        return [$links, $document];
    }
}
