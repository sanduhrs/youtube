<?php

/**
 * @file
 * Contains \Drupal\youtube\Form\SettingsForm.
 */

namespace Drupal\youtube\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\UrlGenerator;
use Drupal\Core\State;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Google_Client;
use Google_Service_YouTube;
use Exception;
use Google_Exception;
use Google_ServiceException;

/**
 * Defines a form that configures devel settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   *
   * @see https://console.developers.google.com
   * @see https://developers.google.com/oauthplayground/
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $settings = $this->config('youtube.settings');

    $client = new Google_Client();
    $client->setScopes('https://www.googleapis.com/auth/youtube');
    $client->setAccessType('offline');
    $client->setApprovalPrompt('force');

    // Get access token.
    // \Drupal::state()->set('youtube.oauth2_access_token', '');
    $access_token = \Drupal::state()->get('youtube.oauth2_access_token');

    if (!empty($settings->get('oauth2_client_id')) &&
      !empty($settings->get('oauth2_client_secret'))) {

      try {
        $client->setClientId($settings->get('oauth2_client_id'));
        $client->setClientSecret($settings->get('oauth2_client_secret'));
        $client->setRedirectUri(\Drupal::url('youtube.admin_settings', array(), array('absolute' => TRUE)));

        // Obtain access token from redirect response.
        if (isset($_GET['code'])) {
          $client->authenticate($_GET['code']);
          $access_token = $client->getAccessToken();
          \Drupal::state()->set('youtube.oauth2_access_token', $access_token);

          // Redirect immediately to obscure response parameters.
          return new RedirectResponse(\Drupal::url('youtube.admin_settings'));
        }

        // Set locally stored access token.
        if (!empty($access_token)) {
          $client->setAccessToken($access_token);
        }


      }
      catch (Exception $e) {
        dsm($e->getMessage());
      }
    }

    // Define an object that will be used to make all API requests.
    $youtube = new Google_Service_YouTube($client);

    // Check to ensure that the access token was successfully acquired.
    if ($client->getAccessToken()) {
      try {
        $response = $youtube->search->listSearch('id,snippet', array(
          'q' => 'The IT Crowd',
          'maxResults' => 50,
        ));
        dsm($response);
      }
      catch (Exception $e) {
        dsm($e->getMessage());
      }
    }

    $form['oauth2'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => t('OAuth 2.0 authentication'),
      '#markup' => t('0. Go to https://console.developers.google.com, 1. Create project, 2. Fill in consent screen, 3. Create new OAuth Client ID, Web application, redirect uri. Then fill in the form below and <a href="!link">Create access token</a>', array('!link' => Url::fromUri($client->createAuthUrl())->getUri())),
    );
    $form['oauth2']['oauth2_client_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Client ID'),
      '#description' => t('The OAuth 2.0 client id.'),
      '#default_value' => $settings->get('oauth2_client_id'),
    );
    $form['oauth2']['oauth2_client_secret'] = array(
      '#type' => 'textfield',
      '#title' => t('Client Secret'),
      '#description' => t('The OAuth 2.0 client secret.'),
      '#default_value' => $settings->get('oauth2_client_secret'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'youtube_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('youtube.settings')
      ->set('oauth2_client_id', $values['oauth2_client_id'])
      ->set('oauth2_client_secret', $values['oauth2_client_secret'])
      ->save();
  }
}
