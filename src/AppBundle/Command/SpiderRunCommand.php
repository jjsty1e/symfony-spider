<?php

/**
 * Created by PhpStorm.
 * User: Jaggle
 * Date: 2017-05-04
 * Time: 14:50
 */

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 开启爬虫master任务
 *
 * Class SpiderRunCommand
 * @package AppBundle\Command
 */
class SpiderRunCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('spider:run');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    
    }
}
