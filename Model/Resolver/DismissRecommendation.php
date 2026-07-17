<?php
/**
 * Esmerio Neto
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future.
 *
 * @category Egsn
 * @package Egsn_StoreIntelligence
 *
 * @copyright Copyright (c) 2026 Esmerio Neto.
 *
 * @author Esmerio Neto <esmerioneto@gmail.com>
 */
declare(strict_types=1);

namespace Egsn\StoreIntelligence\Model\Resolver;

use Egsn\StoreIntelligence\Api\AnalysisRepositoryInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class DismissRecommendation implements ResolverInterface
{
    /**
     * Constructor.
     *
     * @param AnalysisRepositoryInterface $repository
     */
    public function __construct(private readonly AnalysisRepositoryInterface $repository)
    {
    }

    /**
     * Resolve.
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array $value
     * @param array $args
     * @return array
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        if ($context->getUserType() !== UserContextInterface::USER_TYPE_ADMIN) {
            throw new GraphQlAuthorizationException(__('Admin access required'));
        }
        return ['success' => $this->repository->dismissRecommendation((int) ($args['id'] ?? 0))];
    }
}
