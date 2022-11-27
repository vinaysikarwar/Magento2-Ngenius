<?php

namespace NetworkInternational\NGenius\Gateway\Command;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;

/**
 * Class CaptureStrategyCommand
 */
class CaptureStrategyCommand implements CommandInterface
{

    /**
     * N-Genius sale
     */
    const SALE = 'sale';

    /**
     * N-Genius purchase
     */
    const PURCHASE = 'purchase';

    /**
     * N-Genius capture
     */
    const CAPTURE = 'settlement';

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * Constructor
     *
     * @param CommandPoolInterface $commandPool
     */
    public function __construct(
        CommandPoolInterface $commandPool
    ) {
        $this->commandPool = $commandPool;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $commandSubject)
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $command = $this->getCommand($paymentDO);
        $this->commandPool->get($command)->execute($commandSubject);
    }

    /**
     * Gets command name.
     *
     * @param PaymentDataObjectInterface $paymentDO
     * @return string
     */
    private function getCommand(PaymentDataObjectInterface $paymentDO)
    {
        $payment = $paymentDO->getPayment();
        ContextHelper::assertOrderPayment($payment);

        if (!$payment->getAuthorizationTransaction()) {
            return self::SALE;
        } else {
            return self::CAPTURE;
        }
    }
}
