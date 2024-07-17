<?php

class Marktjagd_Entity_Email {

    /* @var string */
    protected $_uniqueId;
    
    /* @var string */
    protected $_messageId;
    
    /* @var string */
    protected $_fromAddress;
    
    /* @var string */
    protected $_toAddress;
    
    /* @var string */
    protected $_sendDate;
    
    /* @var int */
    protected $_size;
    
    /* @var string */
    protected $_subject;
    
    /* @var string */
    protected $_text;
    
    /* @var array */
    protected $_localAttachmentPath;
    
    /**
     * @param string $uniqueId
     * @return Marktjagd_Entity_Email
     */
    public function setUniqueId($uniqueId) {
        $this->_uniqueId = $uniqueId;
        return $this;
    }

    /**
     * @return string
     */
    public function getUniqueId() {
        return $this->_uniqueId;
    }
    
    /**
     * 
     * @param string $messageId
     * @return Marktjagd_Entity_Email
     */
    public function setMessageId($messageId) {
        $this->_messageId = $messageId;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessageId() {
        return $this->_messageId;
    }
    
    /**
     * @param string $fromAddress
     * @return Marktjagd_Entity_Email
     */
    public function setFromAddress($fromAddress) {
        $this->_fromAddress = $fromAddress;
        return $this;
    }

    /**
     * @return string
     */
    public function getFromAddress() {
        return $this->_fromAddress;
    }

    /**
     * @param  array $localAttachmentPath
     * @return Marktjagd_Entity_Email
     */
    public function setLocalAttachmentPath($localAttachmentKey, $localAttachmentPathValue) {
        $this->_localAttachmentPath[$localAttachmentKey] = $localAttachmentPathValue;
        return $this;
    }

    /**
     * @return array
     */
    public function getLocalAttachmentPath() {
        return $this->_localAttachmentPath;
    }

    /**
     * @param string $sendDate
     * @return Marktjagd_Entity_Email
     */
    public function setSendDate($sendDate) {
        $this->_sendDate = date('Y-m-d H:i:s', strtotime($sendDate));
        return $this;
    }

    /**
     * @return string
     */
    public function getSendDate() {
        return $this->_sendDate;
    }

    /**
     * @param int $size
     * @return Marktjagd_Entity_Email
     */
    public function setSize($size) {
        $this->_size = $size;
        return $this;
    }

    /**
     * @return int
     */
    public function getSize() {
        return $this->_size;
    }

    /**
     * @param string $subject
     * @return Marktjagd_Entity_Email
     */
    public function setSubject($subject) {
        $this->_subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject() {
        return $this->_subject;
    }
    
    /**
     * @param string $text
     * @return Marktjagd_Entity_Email
     */
    public function setText($text) {
        $this->_text = $text;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getText() {
        return $this->_text;
    }
    
    /**
     * @param string $toAddress
     * @return Marktjagd_Entity_Email
     */
    public function setToAddress($toAddress) {
        $this->_toAddress = $toAddress;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getToAddress() {
        return $this->_toAddress;
    }
    
    /**
     * @return string
     */
    public function getHash() {
        $hash = md5(
                $this->getFromAddress()
                . $this->getToAddress()
                . $this->getSendDate()
                . $this->getSubject());
        return $hash;
    }
}
