<?php
namespace Drupal\cs_cart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\commerce\commerce_product;
use Drupal\commerce;
use Drupal\commerce_cart;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
 
/**
* Controller routines for products routes.
*/
class CartsController extends ControllerBase {

  /**
  * The cart manager.
  *
  * @var \Drupal\commerce_cart\CartManagerInterface
  */
  protected $cartManager;

  /**
  * The cart provider.
  *
  * @var \Drupal\commerce_cart\CartProviderInterface
  */
  protected $cartProvider;

  /**
  * Constructs a new CartController object.
  *
  * @param \Drupal\commerce_cart\CartProviderInterface $oCartProvider
  *   The cart provider.
  */
  public function __construct(CartManagerInterface $oCartManager, CartProviderInterface $oCartProvider) {
    $this->cartManager                                     = $oCartManager;
    $this->cartProvider                                    = $oCartProvider;
  }

  /**
  * {@inheritdoc}
  */
  public static function create(ContainerInterface $oContainer) {
  return new static(
    $oContainer->get('commerce_cart.cart_manager'),
    $oContainer->get('commerce_cart.cart_provider')
  );
  }

  /**
   * Adapted from...
   * @see: https://www.valuebound.com/resources/blog/how-to-add-a-product-programmatically-to-drupal-commerce-cart
   *
   * @param                                                {string}            $sProductVariationId
   *
   * @return                                               \Symfony\Component\HttpFoundation\RedirectResponse
   * @throws                                               \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws                                               \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addToCart($sProductVariationId) {
    $iProductVariationId                                   = (int)$sProductVariationId;
    $sDefaultOrderType                                     = 'default';
    /* @var                                                CurrentStoreInterface    $oCurrentStore */
    $oCurrentStore                                         = \Drupal::service('commerce_store.current_store');

    $oVariation                                            = \Drupal::entityTypeManager()
      ->getStorage('commerce_product_variation')
      ->load($iProductVariationId)
    ;
    $oStore                                                = $oCurrentStore->getStore();

    $oCart                                                 = $this->cartProvider->getCart($sDefaultOrderType, $oStore);
    if (!$oCart) {
      $oCart                                               = $this->cartProvider->createCart($sDefaultOrderType, $oStore);
    }

    $oLineItemTypeStorage                                  = \Drupal::entityTypeManager()
      ->getStorage('commerce_order_item_type')
    ;

    // Process to place order programatically.
    $oCartManager                                          = \Drupal::service('commerce_cart.cart_manager');
    $oLineItem                                             = $oCartManager->addEntity($oCart, $oVariation);

    $oResponse                                             = new RedirectResponse(\Drupal\core\Url::fromRoute('cs_cart.search_page')->toString());
return $oResponse;
  }

  /**
   * Provides the cart page.
   */
  /**
   * Taken from Commerce 2 CartController.php
   * Outputs a cart view for each non-empty cart belonging to the current user.
   *
   * @return                                               array                                   A render array.
   *
   * @throws                                               \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws                                               \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function cartPage() {
    $aBuild                                                = [];
    $oCacheableMetaData                                    = new CacheableMetadata();
    $oCacheableMetaData->addCacheContexts(['user', 'session']);

    $aCarts                                                = $this->cartProvider->getCarts();
    $aCarts                                                = array_filter($aCarts, function($oCart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $oCart */
    return $oCart->hasItems();
    });

    if (!empty($aCarts)) {
      $aCartViews                                          = $this->getCartViews($aCarts);
      foreach ($aCarts as $iCartId => $oCart) {
        $aBuild[$iCartId]                                  = [
          '#prefix'                                        => '<div class="cart cart-form">',
          '#suffix'                                        => '</div>',
          '#type'                                          => 'view',
          '#name'                                          => $aCartViews[$iCartId],
          '#arguments'                                     => [$iCartId],
          '#embed'                                         => TRUE,
        ];
        $oCacheableMetaData->addCacheableDependency($oCart);
      }
    } else {
      $aBuild['empty']                                     = [
        '#theme'                                           => 'commerce_cart_empty_page',
      ];
    }
    $aBuild['#cache']                                      = [
      'contexts'                                           => $oCacheableMetaData->getCacheContexts(),
      'tags'                                               => $oCacheableMetaData->getCacheTags(),
      'max-age'                                            => $oCacheableMetaData->getCacheMaxAge(),
    ];

  return $aBuild;
  }

  /**
   * Adapted from...
   * @see: https://www.valuebound.com/resources/blog/how-to-add-a-product-programmatically-to-drupal-commerce-cart
   *
   * N.B.: ONLY works for store 1!!
   */
  public function emptyCart() {
    $oResponse                                             = new RedirectResponse(\Drupal\core\Url::fromRoute('cs_cart.page')->toString());

    $sDefaultOrderType                                     = 'default';
    /* @var                                                CurrentStoreInterface    $oCurrentStore */
    $oCurrentStore                                         = \Drupal::service('commerce_store.current_store');

    $oStore                                                = $oCurrentStore->getStore();

    $oCart                                                 = $this->cartProvider->getCart($sDefaultOrderType, $oStore);
    if (!$oCart) {
  return $oResponse;                                                                               // Nothing to empty!
    }

    // Process to place order programatically.
    $oCartManager                                          = \Drupal::service('commerce_cart.cart_manager');
    $oCartManager->emptyCart($oCart);

  return $oResponse;
  }

  /**
   * Provides the cart page.
   */
  /**
   * Taken from Commerce 2 CartController.php
   * Outputs a cart view for each non-empty cart belonging to the current user.
   *
   * @return                                               object
   */
  public function getCartItems() {
    $oResponse                                             = new RedirectResponse('/cart');

    $sDefaultOrderType                                     = 'default';
    /* @var                                                CurrentStoreInterface    $oCurrentStore */
    $oCurrentStore                                         = \Drupal::service('commerce_store.current_store');
    $oStore                                                = $oCurrentStore->getStore();
    $oCartProvider                                         = \Drupal::service('commerce_cart.cart_provider');
    $oCart                                                 = $oCartProvider->getCart($sDefaultOrderType, $oStore);
    if (!$oCart) {
  return null;                                                                               // Nothing to empty!
    }

    if ($oCart->hasItems()) {
  return $oCart;
    } else {
  return null;
    }
  }

  /**
   * Gets the cart views for each cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface[] $aCarts
   *   The cart orders.
   *
   * @return                                               array                                   An array of view ids keyed by iCardId.
   * @throws                                               \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws                                               \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getCartViews(array $aCarts) {
    $aOrderTypeIds                                         = array_map(function($oCart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $oCart */
    return $oCart->bundle();
    }, $aCarts);
    $oOrderTypeStorage                                     = $this->entityTypeManager()
      ->getStorage('commerce_order_type')
    ;
    $aOrderTypes                                           = $oOrderTypeStorage->loadMultiple(array_unique($aOrderTypeIds));
    $aCartViews                                            = [];
    foreach ($aOrderTypeIds as $iCartId => $iOrderTypeId) {
      /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $oOrderType */
      $oOrderType                                          = $aOrderTypes[$iOrderTypeId];
      $aCartViews[$iCartId]                                = $oOrderType->getThirdPartySetting('commerce_cart', 'cart_form_view', 'commerce_cart_form');
    }

  return $aCartViews;
  }
}
