<?php

/**
 * Created by PhpStorm.
 * User: jake
 * Date: 2017/5/6
 * Time: 上午9:24
 */

namespace AppBundle\Command;

use AppBundle\Entity\Document;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 该任务队列主要作用是数据的入库操作，
 * 避免数据库的锁和竞争导致等待甚至可能产生的锁超时的问题
 *
 * Class QueueRunCommand
 * @package AppBundle\Command
 */
class QueueRunCommand extends ContainerAwareCommand
{
    /**
     * @var string 当前队列的版本号
     */
    private $queueVersion;

    /**
     * @var SymfonyStyle
     */
    private $io;

    protected function configure()
    {
        $this->setName('queue:run');
        $this->addArgument('queueName');
        $this->addOption('spiderName', null, InputOption::VALUE_REQUIRED);
        $this->addOption('queueVersion', null, InputOption::VALUE_REQUIRED);
    }

    /**
     * @return \Doctrine\Bundle\DoctrineBundle\Registry
     */
    protected function getDoctrine()
    {
        return $this->getContainer()->get('doctrine');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $redis = $this->getContainer()->get('snc_redis.cache');

        $this->queueVersion = $input->getOption('queueVersion');
        $this->io = new SymfonyStyle($input, $output);

        $result = $redis->set('spider:queue-version', $this->queueVersion);

        if (!$result) {
            $this->io->warning('[队列] redis连接错误');
            return ;
        }

        $queueName = $input->getArgument('queueName');

        if ($queueName == 'job') {
            $this->runJobQueue();
        } elseif ($queueName == 'document') {
            $this->runDocumentQueue();
        }
    }

    /**
     * job任务
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function runJobQueue()
    {
        $redis = $this->getContainer()->get('snc_redis.cache');
        $spiderService = $this->getContainer()->get('app.spider.service');

        $jobRepository = $this->getDoctrine()->getRepository('AppBundle:Job');

        $this->io->success('[任务队列] 任务队列已经启动！');

        while (1) {
            if ($redis->get('spider:queue-version') != $this->queueVersion) {
                $this->io->warning('[任务队列] 版本号被修改了！');
                return ;
            }

            $redisJob = $redis->lpop('spider:job-queue');

            if (!$redisJob) {
                sleep(5);
                continue;
            }

            $jobData = json_decode($redisJob, true);

            $spiderId = $jobData['spiderId'];
            $link = $jobData['link'];

            $job = $jobRepository->findOneBy(['spiderId' => $spiderId, 'link' => $link]);

            if ($job) {
                continue;
            }

            $job = $jobRepository->createJob($jobData['spiderId'], $jobData['link']);
            $spiderService->refreshRedisWaitingJobs($spiderId, $job->getId());

            sleep(1);
        }
    }

    /**
     * 文档任务，主要是进行文档的入库操作
     */
    public function runDocumentQueue()
    {
        $redis = $this->getContainer()->get('snc_redis.cache');
        $documentRepository = $this->getDoctrine()->getRepository('AppBundle:Document');
        $spiderService = $this->getContainer()->get('app.spider.service');
        $this->io->success('[文档队列] 队列已启动');

        while (1) {
            // 如果发现版本被修改，则退出
            if ($redis->get('spider:queue-version') != $this->queueVersion) {
                $this->io->warning('[文档队列] 队列版本号被修改了！');
                return ;
            }
            
            $redisDocument = $redis->lpop('spider:document-queue');

            if (!$redisDocument) {
                sleep(5);
                continue;
            }

            $jobData = json_decode($redisDocument, true);

            $jobId = $jobData['jobId'];
            $spiderId = $jobData['spiderId'];
            $link = $jobData['link'];
            $title = $jobData['title'];
            $meta = $jobData['meta'];
            $desc = $jobData['desc'];
            $content = $jobData['content'];

            /**
             * 任务已经有了对应的文档
             */
            if ($documentRepository->getDocumentByJobId($jobId)) {
                $spiderService->finishJob($jobId);
                $this->io->warning('[文档队列] 该任务已经被执行，对应的文档已经创建');
                continue;
            }

            /**
             * @var Document $document
             */
            $document = $documentRepository->findOneBy(['title' => $title]);

            if ($document) {
                $spiderService->finishJob($jobId);
                $this->io->warning('[文档队列] 已经有相同标题的文档! : ' . $document->getLink() . '. 新链接是: ' . $link);
                continue;
            }

            $documentRepository->createDocument($title, $jobId, $spiderId, $meta, $link, $content, $desc);

            $spiderService->finishJob($jobId);

            $this->io->success('[文档队列] 文档已经入库: ' . $link);
        }
    }
}