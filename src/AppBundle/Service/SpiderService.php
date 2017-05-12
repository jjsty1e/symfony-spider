<?php

/**
 * Created by PhpStorm.
 * User: jake
 * Date: 2017/5/7
 * Time: 上午9:44
 */

namespace AppBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SpiderService
{
    /**
     * @var ContainerInterface
     */
    private  $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return Registry
     */
    public function getDoctrine()
    {
        return $this->container->get('doctrine');
    }

    /**
     * 完成一个job
     *
     * @param $jobId
     */
    public function finishJob($jobId)
    {
        $jobRepository = $this->getDoctrine()->getRepository('AppBundle:Job');
        $job = $jobRepository->find($jobId);
        $jobRepository->finishJob($job);

        $redis = $this->container->get('snc_redis.cache');
        $redis->srem('spider:waiting-job', $job->getId());
    }

    /**
     * 当有新的job创建时需要更新redis中等待执行的jobIds
     *
     * @param $spiderId
     * @param $jobId
     */
    public function refreshRedisWaitingJobs($spiderId, $jobId)
    {
        $jobRepository = $this->getDoctrine()->getRepository('AppBundle:Job');
        $redis = $this->container->get('snc_redis.cache');

        $jobIds = $jobRepository->getAllUnProcessJobIds($spiderId, 'bang/info');

        if ($jobIds) {
            $redis->sadd('spider:waiting-job', [$jobId]);

        }
    }
    
    /**
     * @param array $jobData
     */
    public function pushRedisDocument(array $jobData)
    {
        $redis = $this->container->get('snc_redis.cache');

        if (!empty($jobData)) {
            $redis->lpush('spider:document-queue', json_encode($jobData));
        }
    }
    
    /**
     * @param array $jobs
     */
    public function pushRedisJob(array $jobs)
    {
        $redis = $this->container->get('snc_redis.cache');

        if (!empty($jobs)) {
            $redis->lpush('spider:job-queue', $jobs);
        }
    }

    /**
     * redis - 创建等待处理的job的集合
     *
     * @param int $spiderId
     */
    public function createWaitingJobSet($spiderId)
    {
        $jobRepository = $this->getDoctrine()->getRepository('AppBundle:Job');

        $redis = $this->container->get('snc_redis.cache');

        $jobIds = $jobRepository->getAllUnProcessJobIds($spiderId, 'bang/info');

        if ($jobIds) {
            $redis->sadd('spider:waiting-job', $jobIds);
        }
    }
}
