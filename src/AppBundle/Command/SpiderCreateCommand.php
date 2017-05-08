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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //$io = new SymfonyStyle($input, $output);

        // spider 的名字

        // spider 的主页

        // 是否同时允许 https 和http

        // 是否同时允许 wwww 和不带www

        // 是否 考虑其他子站点 ，比如  Beijing.xxx.com


    }
}
