<?php

/**
 * Created by PhpStorm.
 * User: jake
 * Date: 2017/5/9
 * Time: 下午10:21
 */

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearDoctrineCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('clear:doctrine');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $redis = $this->getContainer()->get('snc_redis.cache');

        $keys = $redis->keys('*[1]*');

        if ($keys) {
            $redis->del($keys);
        }
    }
}
