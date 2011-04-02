[?php use_helper('I18N', 'Date') ?]
[?php include_partial('<?php echo $this->getModuleName() ?>/assets') ?]

<div id="sf_admin_container">
  <h1>[?php echo <?php echo $this->getI18NString('edit.title') ?> ?]</h1>

  [?php include_partial('<?php echo $this->getModuleName() ?>/flashes') ?]

  <div id="sf_admin_header">
    [?php include_partial('<?php echo $this->getModuleName() ?>/form_header', array('<?php echo $this->getSingularName() ?>' => $<?php echo $this->getSingularName() ?>, 'form' => $form, 'configuration' => $configuration)) ?]
  </div>

  <div id="sf_admin_content">

    [?php include_partial(
      '<?php echo $this->getModuleName() ?>/form',
      array(
        '<?php echo $this->getSingularName() ?>' => $<?php echo $this->getSingularName() ?>,
        'form' => $form,
        'configuration' => $configuration,
        'helper' => $helper,
        'sf_cache_key' => sprintf(
          'backend-form-module:%s-id:%d',
          '<?php echo $this->getModuleName() ?>',
          $sf_data->getRaw('form')->getObject()->getId()
        ), 
        'sf_cache_tags' => $form->getObject()->getCacheTags(),
      )
    ) ?]
  </div>

  <div id="sf_admin_footer">
    [?php include_partial('<?php echo $this->getModuleName() ?>/form_footer', array('<?php echo $this->getSingularName() ?>' => $<?php echo $this->getSingularName() ?>, 'form' => $form, 'configuration' => $configuration)) ?]
  </div>
</div>
