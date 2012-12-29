<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * sfCacheTaggingPlugin configuration
   *
   * @package sfCacheTaggingPlugin
   * @subpackage config
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   * @property sfEventDispatcher $dispatcher Event dispatcher instance
   */
  class sfCacheTaggingPluginConfiguration extends sfPluginConfiguration
  {
    /**
     * Configure required listeners for proper plugin functionality
     *
     * {@inheritdoc}
     */
    public function __construct (sfProjectConfiguration $configuration, $rootDir = null, $name = null)
    {
      parent::__construct($configuration, $rootDir, $name);

      // Enable tags rollback/commit support inside Doctrine transactions
      $this->connectToEvent('doctrine.configure');

      // suppress custom sfAction method call without fail
      $this->connectToEvent('component.method_not_found');

      // Setup plugin's model class "sfCachetaggableDoctrineRecord"
      $this->connectToEvent('doctrine.filter_model_builder_options');

      // Enable tags rollback/commit support inside Doctrine transactions
      $this->connectToEvent('doctrine.configure_connection');
    }

    /**
     * Binds callback on the passed event name
     *
     * @param string $eventName
     * @return sfCacheTaggingPluginConfiguration
     */
    protected function connectToEvent ($eventName)
    {
      $camelized = sfInflector::camelize(str_replace('.', '_', $eventName));
      $this->dispatcher->connect($eventName, array($this, "listenOn{$camelized}Event"));

      return $this;
    }

    /**
     * Triggers on "component.method_not_found" event
     *
     * @param sfEvent $event
     * @return null
     */
    public function listenOnComponentMethodNotFoundEvent (sfEvent $event)
    {
      try
      {
        $component = $event->getSubject();
        /* @var $component sfComponent */

        $event->setReturnValue(
          call_user_func_array(
            array(new sfViewCacheTagManagerBridge($component), $event['method']),
            $event['arguments']
          )
        );

        $event->setProcessed(true); // ok, this is the end
      }
      catch (BadMethodCallException $e)
      {
        // process not finished, unknown method, or cache was disabled
        $event->setProcessed(false);
      }
      catch (sfException $e) // throws by sfCallable
      {
        // process is finished, method was valid, but call generates an error
        $event->setProcessed(true);
      }
    }

    /**
     * Triggers on "doctrine.configure_connection" event
     *
     * @param sfEvent $event
     */
    public function listenOnDoctrineConfigureConnectionEvent (sfEvent $event)
    {
      if (! sfConfig::get('sf_cache')) return;

      $connection = $event['connection'];
      /* @var $connection Doctrine_Connection */

      $connection->addListener(
        new Doctrine_EventListener_Cachetaggable(), 'cache_tagging'
      );
    }

    /**
     * Triggers on "doctrine.filter_model_builder_options" event
     *
     * @param sfEvent $event
     * @param array $params
     * @return array
     */
    public function listenOnDoctrineFilterModelBuilderOptionsEvent (sfEvent $event, array $params)
    {
      return array_merge($params, array('baseClassName' => 'sfCachetaggableDoctrineRecord'));
    }

    /**
     * Configures Doctrine manager.
     *
     * Connecting to doctrine.configure event will not work, if sfDoctrinePlugin
     * declared before sfCacheTaggingPlugin.
     *
     * @return null
     */
    public function listenOnDoctrineConfigureEvent (sfEvent $event)
    {
      $manager = $event->getSubject();
      /* @var $manager Doctrine_Manager */

      // collection with additional methods to with tags
      $manager->setAttribute(
        Doctrine_Core::ATTR_COLLECTION_CLASS, 'Doctrine_Collection_Cachetaggable'
      );

      // Enable Doctrine result query cache only if cache is enabled
      if (sfConfig::get('sf_cache'))
      {
        $manager->setAttribute(
          Doctrine_Core::ATTR_RESULT_CACHE, new Doctrine_Cache_Proxy()
        );

        $manager->setAttribute(
          Doctrine_Core::ATTR_QUERY_CLASS, 'Doctrine_Query_Cachetaggable'
        );
      }

      // Enable DQL callbacks because Doctrine model can actAs Cachetagging behavior
      $manager->setAttribute(Doctrine::ATTR_USE_DQL_CALLBACKS, true);
    }
  }
