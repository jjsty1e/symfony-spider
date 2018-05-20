<?php

/**
 * Created by PhpStorm.
 * User: jake
 * Date: 2017/5/7
 * Time: 上午9:44
 */

namespace AppBundle\Service;

use AppBundle\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SpiderService
{
    /**
     * @var ContainerInterface
     */
    private  $container;
    
    /**
     * @var array 爬虫规则
     */
    private $rules;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->rules = $this->getRules();
    }

    /**
     * @return Registry
     */
    public function getDoctrine()
    {
        return $this->container->get('doctrine');
    }
    
    /**
     * @param string $spiderName
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function getRules($spiderName = '')
    {
        $ruleFile = $this->container->getParameter('kernel.root_dir') . '/config/' . 'rules.json';
        $rules = file_get_contents($ruleFile);
        
        $rules = json_decode($rules, true);
        
        if (!empty($spiderName)) {
    
            if (empty($rules[$spiderName])) {
                throw new \Exception('rule for spider: ' . $spiderName . 'not exist!');
            }
    
            return $rules[$spiderName];
        }
        
        return $rules;
    }

    /**
     * 完成一个job
     *
     * @param $jobId
     * @return Job|null|object
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function finishJob($jobId)
    {
        $jobRepository = $this->getDoctrine()->getRepository('AppBundle:Job');
        $job = $jobRepository->find($jobId);
        $jobRepository->finishJob($job);

        $redis = $this->container->get('snc_redis.cache');
        $redis->srem('spider:waiting-job', $job->getId());
        
        return $job;
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
        $spiderRepository = $this->getDoctrine()->getRepository('AppBundle:Spider');
        
        $spider = $spiderRepository->find($spiderId);
        
        $linkRule = $this->rules[$spider->getName()]['linkRule'];
        $query = '';
        
        if ($linkRule['status']) {
            $query = $linkRule['rule'];
        }

        $jobIds = $jobRepository->getAllUnProcessJobIds($spiderId, $query);

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
     * @param string $spiderName
     */
    public function createWaitingJobSet($spiderName)
    {
        $jobRepository = $this->getDoctrine()->getRepository('AppBundle:Job');
        $spiderRepository = $this->getDoctrine()->getRepository('AppBundle:Spider');

        $redis = $this->container->get('snc_redis.cache');
        $spider = $spiderRepository->findOneBy(['name' => $spiderName]);
        
        $linkRule = $this->rules[$spiderName]['linkRule'];
        $query = '';
        
        if ($linkRule['status']) {
            $query = $linkRule['rule'];
        }

        $jobIds = $jobRepository->getAllUnProcessJobIds($spider->getId(), $query);

        if ($jobIds) {
            $redis->sadd('spider:waiting-job', $jobIds);
        }
    }
}
