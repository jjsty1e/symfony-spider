<?php

/**
 * Created by PhpStorm.
 * User: Jaggle
 * Date: 2017-05-04
 * Time: 14:52
 */

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SpiderStopCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('spider:stop');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    
    }
}
