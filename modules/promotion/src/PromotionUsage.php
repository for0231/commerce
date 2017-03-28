<?php

namespace Drupal\commerce_promotion;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\Core\Database\Connection;

class PromotionUsage implements PromotionUsageInterface {

  /**
   * The database connection to use.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a PromotionUsage object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function addUsage(OrderInterface $order, PromotionInterface $promotion, CouponInterface $coupon = NULL) {
    $this->connection->insert('commerce_promotion_usage')
      ->fields([
        'promotion_id' => $promotion->id(),
        'coupon_id' => $coupon ? $coupon->id() : 0,
        'order_id' => $order->id(),
        'mail' => $order->getEmail(),
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUsage(array $promotions) {
    $promotion_ids = array_map(function ($promotion) {
      /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
      return $promotion->id();
    }, $promotions);
    $this->connection->delete('commerce_promotion_usage')
      ->condition('promotion_id', $promotion_ids, 'IN')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getUsage(PromotionInterface $promotion, CouponInterface $coupon = NULL, $mail = NULL) {
    $coupons = $coupon ? [$coupon] : [];
    $usages = $this->getUsageMultiple([$promotion], $coupons, $mail);
    return $usages[$promotion->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function getUsageMultiple(array $promotions, array $coupons = [], $mail = NULL) {
    $promotion_ids = array_map(function ($promotion) {
      /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
      return $promotion->id();
    }, $promotions);
    $query = $this->connection->select('commerce_promotion_usage', 'cpu');
    $query->addField('cpu', 'promotion_id');
    $query->addExpression('COUNT(promotion_id)', 'count');
    $query->condition('promotion_id', $promotion_ids, 'IN');
    if (!empty($coupons)) {
      $coupon_ids = array_map(function ($coupon) {
        /** @var \Drupal\commerce_promotion\Entity\CouponInterface $coupon */
        return $coupon->id();
      }, $coupons);
      $query->condition('coupon_id', $coupon_ids, 'IN');
    }
    if (!empty($mail)) {
      $query->condition('mail', $mail);
    }
    $query->groupBy('promotion_id');
    $result = $query->execute()->fetchAllAssoc('promotion_id', \PDO::FETCH_ASSOC);
    // Ensure that each promotion ID gets a count, even if it's not present
    // in the query due to non-existent usage.
    $counts = [];
    foreach ($promotion_ids as $promotion_id) {
      $counts[$promotion_id] = 0;
      if (isset($result[$promotion_id])) {
        $counts[$promotion_id] = $result[$promotion_id]['count'];
      }
    }

    return $counts;
  }

}
