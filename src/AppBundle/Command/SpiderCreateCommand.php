<?php

/**
 * Created by PhpStorm.
 * User: jake
 * Date: 2017/5/6
 * Time: 上午11:29
 */

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 创建一个新的spider
 *
 * TODO
 *
 * Class SpiderCreateCommand
 * @package AppBundle\Command
 */
class SpiderCreateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('spider:create');
    }
    
    /**
     * @return \Doctrine\Bundle\DoctrineBundle\Registry
     */
    public function getDoctrine()
    {
        return $this->getContainer()->get('doctrine');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        
        do {
            $spiderName = $io->ask((isset($spiderName) ? 'spider name invalid! ' : '') . 'the spider name (only one word please)', 'default');
        } while(strpos($spiderName, ' ') !== false);
        
        do {
            $site = $io->ask((isset($site) ? 'site invalid! ' : '') . 'spider gateway, usually the homepage like: https://www.zhihu.com');
            $siteData = parse_url($site);
        } while(empty($siteData['host']));
        
        if (strpos($siteData['host'], '.com.cn') !== false) {
           preg_match('#\.?([^\.]*\.com.cn$)#', $siteData['host'], $match);
        } else {
            preg_match('#\.?([^\.]*\.[^\.]*$)#', $siteData['host'], $match);
        }
    
        $siteDomain = $match[1];
        
        $spiderRepository = $this->getDoctrine()->getRepository('AppBundle:Spider');
        $jobRepository = $this->getDoctrine()->getRepository('AppBundle:Job');
        
        $spider = $spiderRepository->createSpider($spiderName, $site, $siteDomain);
        
        $jobRepository->createJob($spider->getId(), $spider->getSite());
        
        $io->success('Created spider: ' . $spider->getName());
    }
}
