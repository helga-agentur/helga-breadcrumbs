<?php

declare(strict_types=1);

namespace Drupal\helga_breadcrumbs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Entity\Menu;

/**
 * Configure Helga Breadcrumbs settings for this site.
 */
final class HelgaBreadcrumbSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'helga_breadcrumbs_helga_breadcrumb_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *  An array of configuration object names that will be editable by this form.
   */
  protected function getEditableConfigNames(): array {
    return ['helga_breadcrumbs.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed[] $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return mixed[]
   *   An associative array containing the structure of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    foreach (Menu::loadMultiple() as $menu) {
      $options[$menu->id()] = $menu->label();
    }
    $form['breadcrumbs_orphans_menu'] = [
      '#type' => 'select',
      '#title' => $this->t("Orphan's menu"),
      '#default_value' => $this->config('helga_breadcrumbs.settings')->get('breadcrumbs_orphans_menu'),
      '#empty_value' => '',
      '#options' => $options,
      '#description' => $this->t('Select the menu to use for orphan breadcrumbs.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed[] $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('helga_breadcrumbs.settings')
      ->set('breadcrumbs_orphans_menu', $form_state->getValue('breadcrumbs_orphans_menu'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
