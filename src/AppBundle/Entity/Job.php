<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Job
 *
 * @ORM\Table(name="job")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\JobRepository")
 */
class Job
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="link", type="string", length=255)
     */
    private $link;

    /**
     * @var int
     *
     * @ORM\Column(name="status", type="integer", options={"comment":"0：从未执行过；1：正在执行；2：执行完成"})
     */
    private $status;

    /**
     * @var int
     *
     * @ORM\Column(name="categoryId", type="integer", options={"comment":"属于哪个分类, 如果是0，则表示认为从首页爬取而来", "default":0})
     */
    private $categoryId;
    
    /**
     * @var integer
     *
     * @ORM\Column(name="spider_id", type="integer", options={"comment":"属于哪个爬虫的id"})
     */
    private $spiderId;
    
    /**
     * @var boolean
     *
     * @ORM\Column(name="failed", type="boolean", options={"comment":"执行是否失败"})
     */
    private $failed;

    /**
     * @var int
     *
     * @ORM\Column(name="retry", type="integer")
     */
    private $retry;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="create_time", type="datetime")
     */
    private $createTime;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updateTime", type="datetime")
     */
    private $updateTime;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set link
     *
     * @param string $link
     * @return Job
     */
    public function setLink($link)
    {
        $this->link = $link;

        return $this;
    }

    /**
     * Get link
     *
     * @return string 
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * Set status
     *
     * @param integer $status
     * @return Job
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return integer 
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set categoryId
     *
     * @param integer $categoryId
     * @return Job
     */
    public function setCategoryId($categoryId)
    {
        $this->categoryId = $categoryId;

        return $this;
    }

    /**
     * Get categoryId
     *
     * @return integer 
     */
    public function getCategoryId()
    {
        return $this->categoryId;
    }

    /**
     * Set retry
     *
     * @param integer $retry
     * @return Job
     */
    public function setRetry($retry)
    {
        $this->retry = $retry;

        return $this;
    }

    /**
     * Get retry
     *
     * @return integer 
     */
    public function getRetry()
    {
        return $this->retry;
    }

    /**
     * Set createTime
     *
     * @param \DateTime $createTime
     * @return Job
     */
    public function setCreateTime($createTime)
    {
        $this->createTime = $createTime;

        return $this;
    }

    /**
     * Get createTime
     *
     * @return \DateTime 
     */
    public function getCreateTime()
    {
        return $this->createTime;
    }

    /**
     * Set updateTime
     *
     * @param \DateTime $updateTime
     * @return Job
     */
    public function setUpdateTime($updateTime)
    {
        $this->updateTime = $updateTime;

        return $this;
    }

    /**
     * Get updateTime
     *
     * @return \DateTime 
     */
    public function getUpdateTime()
    {
        return $this->updateTime;
    }
    
    /**
     * @return int
     */
    public function getSpiderId()
    {
        return $this->spiderId;
    }
    
    /**
     * @param int $spiderId
     */
    public function setSpiderId($spiderId)
    {
        $this->spiderId = $spiderId;
    }
    
    /**
     * @return bool
     */
    public function isFailed()
    {
        return $this->failed;
    }
    
    /**
     * @param bool $failed
     */
    public function setFailed($failed)
    {
        $this->failed = $failed;
    }
    
}
