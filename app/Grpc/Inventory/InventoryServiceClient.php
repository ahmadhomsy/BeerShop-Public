<?php

namespace Inventory;

use Grpc\BaseStub;
use Grpc\ChannelCredentials;

/**
 * Native gRPC Service Client for InventoryService.
 */
class InventoryServiceClient extends BaseStub
{
    /**
     * @param string $hostname
     * @param array $opts
     * @param \Grpc\Channel $channel (optional)
     */
    public function __construct(string $hostname, array $opts = [], $channel = null)
    {
        if (!isset($opts['credentials'])) {
            $opts['credentials'] = ChannelCredentials::createInsecure();
        }
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * GetStock RPC
     * @param GetStockRequest $argument
     * @param array $metadata
     * @param array $options
     * @return \Grpc\UnaryCall
     */
    public function GetStock(
        GetStockRequest $argument,
        $metadata = [],
        $options = []
    ) {
        return $this->_simpleRequest(
            '/inventory.InventoryService/GetStock',
            $argument,
            ['\Inventory\GetStockResponse', 'decode'],
            $metadata,
            $options
        );
    }

    /**
     * ReserveStock RPC
     * @param ReserveStockRequest $argument
     * @param array $metadata
     * @param array $options
     * @return \Grpc\UnaryCall
     */
    public function ReserveStock(
        ReserveStockRequest $argument,
        $metadata = [],
        $options = []
    ) {
        return $this->_simpleRequest(
            '/inventory.InventoryService/ReserveStock',
            $argument,
            ['\Inventory\ReserveStockResponse', 'decode'],
            $metadata,
            $options
        );
    }

    /**
     * ReleaseStock RPC
     * @param ReleaseStockRequest $argument
     * @param array $metadata
     * @param array $options
     * @return \Grpc\UnaryCall
     */
    public function ReleaseStock(
        ReleaseStockRequest $argument,
        $metadata = [],
        $options = []
    ) {
        return $this->_simpleRequest(
            '/inventory.InventoryService/ReleaseStock',
            $argument,
            ['\Inventory\ReleaseStockResponse', 'decode'],
            $metadata,
            $options
        );
    }

    /**
     * ConfirmStock RPC
     * @param ConfirmStockRequest $argument
     * @param array $metadata
     * @param array $options
     * @return \Grpc\UnaryCall
     */
    public function ConfirmStock(
        ConfirmStockRequest $argument,
        $metadata = [],
        $options = []
    ) {
        return $this->_simpleRequest(
            '/inventory.InventoryService/ConfirmStock',
            $argument,
            ['\Inventory\ConfirmStockResponse', 'decode'],
            $metadata,
            $options
        );
    }
}
