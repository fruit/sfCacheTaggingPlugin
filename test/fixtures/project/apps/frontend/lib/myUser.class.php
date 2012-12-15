<?php

  class myUser extends sfBasicSecurityUser
  {
    public function getId ()
    {
      return $this->getAttribute('user_id');
    }

    public function initialize (sfEventDispatcher $dispatcher, sfStorage $storage, $options = array())
    {
      parent::initialize($dispatcher, $storage, $options);

      if ('dev' == sfConfig::get('sf_environment'))
      {
        $dispatcher->connect('cache.filter_cache_keys', array($this, 'listenOnCacheFilterCacheKeys'));
      }
    }

    public function listenOnCacheFilterCacheKeys (sfEvent $event, array $params)
    {
      /* @var $user myUser */
      $user = $event->getSubject();

      $userParams =  array(
        'site_id' => 90,
        'user_id' => $user->getAttribute('user_id', 0),
        'type'    => 'BASIC',
        'action'  => 'aaaaaaaaaaa',
      );

      return array_merge($params, $userParams);
    }
  }
