<?php

namespace NetworkInternational\NGenius\Controller\NGeniusOnline;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\LayoutFactory;

/**
 * Class Redirect
 */
class Redirect extends \Magento\Framework\App\Action\Action
{

    /**
     * @var ResultFactory
     */
    protected $resultRedirect;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var LayoutFactory
     */
    protected $layoutFactory;

    /**
     * Redirect constructor.
     *
     * @param Context $context
     * @param ResultFactory $resultRedirect
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        ResultFactory $resultRedirect,
        Session $checkoutSession,
        LayoutFactory $layoutFactory
    ) {
        $this->resultRedirect = $resultRedirect;
        $this->checkoutSession = $checkoutSession;
        $this->layoutFactory = $layoutFactory;
        return parent::__construct($context);
    }

    /**
     * Default execute function.
     *
     * @return ResultFactory
     */
    public function execute()
    {
        $block = $this->layoutFactory->create()->createBlock('NetworkInternational\NGenius\Block\Ngenius');
        $url = $this->checkoutSession->getPaymentURL();

        if(!$url){
            $url = $block->get_payment_url();
        }

        $resultRedirectFactory = $this->resultRedirect->create(ResultFactory::TYPE_REDIRECT);
        if ($url) {
            $resultRedirectFactory->setUrl($url);
        } else {
            $resultRedirectFactory->setPath('checkout');
        }
        $this->checkoutSession->unsPaymentURL();
        return $resultRedirectFactory;
    }
}
