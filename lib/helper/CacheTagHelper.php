<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * CacheHelper.
 *
 * @package    symfony
 * @subpackage helper
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id: CacheHelper.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */

/* Usage

<?php if (!cache('name')): ?>

... HTML ...

  <?php cache_save() ?>
<?php endif; ?>

*/

function cache_tag($name, $lifetime = null)
{
  if (! sfConfig::get('sf_cache'))
  {
    return null;
  }
  
  if (sfConfig::get('symfony.cache.started'))
  {
    throw new sfCacheException('Cache already started.');
  }

  $data = sfContext::getInstance()->getViewCacheManager()->startWithTags($name);

  if (null === $data)
  {
    sfConfig::set('symfony.cache.started', true);
    sfConfig::set('symfony.cache.current_name', $name);
    sfConfig::set('symfony.cache.lifetime', $lifetime);

    return false;
  }
  else
  {
    echo $data;

    return true;
  }
}

function cache_tag_save(array $tags = null)
{
  if (! is_null($tags))
  {
    sfConfig::set('symfony.cache.tags', $tags);
  }

  if (!sfConfig::get('sf_cache'))
  {
    return null;
  }

  if (!sfConfig::get('symfony.cache.started'))
  {
    throw new sfCacheException('Cache not started.');
  }

  $data = sfContext::getInstance()
    ->getViewCacheManager()
    ->stopWithTags(
      sfConfig::get('symfony.cache.current_name', ''),
      sfConfig::get('symfony.cache.lifetime', 86400),
      sfConfig::get('symfony.cache.tags', array())
    );


  sfConfig::set('symfony.cache.started', false);
  sfConfig::set('symfony.cache.tags', null);
  sfConfig::set('symfony.cache.current_name', null);
  sfConfig::set('symfony.cache.lifetime', null);

  echo $data;
}
