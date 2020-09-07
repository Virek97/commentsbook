<?php


  namespace Drupal\commentsbook\Form;

  use Drupal\Core\Form\FormBase;
  use Drupal\Core\Form\FormStateInterface;

  use Drupal\Core\Ajax\AjaxResponse;
  use Drupal\Core\Ajax\HtmlCommand;
  use Drupal\Core\Ajax\RedirectCommand;

  use Drupal\Core\Url;

  /**
   * Class MainForm
   *
   * @package Drupal\commentsbook\Form
   */
  class MainForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
      return 'comments_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
//      If an id has been passed to the method, we extract from the database all comment data with the corresponding id, and set them to the default for the corresponding form fields.
//      If id was not passed, the database is not queried, and an empty string is set for the fields by default.
      if ($id != NULL) {
        $comment = [];
        $query = \Drupal::database()->select('custom_comments', 'n');
        $query->fields('n', ['id', 'name',  'email', 'phone_number', 'comment_text', 'avatar_photo', 'comment_image']);
        $query->condition('id', $id, '=');
        $result = $query->execute()->fetchAll();

        foreach ($result as $item) {
          $comment = [
            'name' => $item->name,
            'email' => $item->email,
            'phone_number' => $item->phone_number,
            'comment_text' => $item->comment_text,
            'avatar_photo' => $item->avatar_photo,
            'comment_image' => $item->comment_image,
          ];
        }

        $form_state->set('id', $id);
        $form_state->set('avatar_photo', $comment['avatar_photo']);
        $form_state->set('comment_image', $comment['comment_image']);
      }

      $form['name'] = [
        '#type' => 'textfield',
        '#title' => 'Ім\'я',
        '#default_value' => $comment['name'] ?? '',
        '#required' => TRUE,
      ];
      $form['email'] = [
        '#type' => 'email',
        '#title' => 'Email',
        '#default_value' => $comment['email'] ?? '',
        '#required' => TRUE,
      ];
      $form['phone_number'] = [
        '#type' => 'tel',
        '#title' => 'Номер телефону',
        '#default_value' => $comment['phone_number'] ?? '',
        '#required' => TRUE,
      ];
      $form['comment_text'] = [
        '#type' => 'textarea',
        '#title' => 'Текст коментаря',
        '#default_value' => $comment['comment_text'] ?? '',
        '#required' => TRUE,
        '#cols' => 60,
        '#rows' => 13,
      ];
      $form['avatar_photo'] = array(
        '#title' => t('Завантажити своє фото'),
        '#type' => 'managed_file',
        '#description' => t('Макимальний розмір файлу 2мб.'),
        '#upload_location' => 'public://comments_images/avatars/',
        '#required' => FALSE,
        '#upload_validators' => [
          'file_validate_extensions' => ['gif png jpg jpeg'],
          'file_validate_size' => [2000000],
          'file_validate_is_image' => [],
        ],
      );
      $form['comment_image'] = array(
        '#title' => t('Завантажити зображення до відгуку'),
        '#type' => 'managed_file',
        '#description' => t('Макимальний розмір файлу 5мб.'),
        '#upload_location' => 'public://comments_images/',
        '#required' => FALSE,
        '#upload_validators' => [
          'file_validate_extensions' => ['gif png jpg jpeg'],
          'file_validate_size' => [5000000],
          'file_validate_is_image' => [],
        ],
      );
      $form['submit'] = [
        '#type' => 'submit',
        '#name' => 'submit',
        '#value' => 'Відправити',
        '#ajax' => [
          'callback' => '::ajaxSubmitCallback',
          'event' => 'click',
          'progress' => [
            'type' => 'throbber',
          ],
        ],
      ];

      return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
//      Regular expression to check the phone number
      $phone_pattern = "/^(\s*)?(\+)?([- _():=+]?\d[- _():=+]?){12}(\s*)?$/";

      if (iconv_strlen($form_state->getValue('comment_text')) < 10) {
        $form_state->setErrorByName('name',
          $this->t('Коментар має бути довжиною від 10 символів!'));
      }
      if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
        $form_state->setErrorByName('name',
          $this->t('Невірний формат email!'));
      }
      if (!preg_match($phone_pattern, $form_state->getValue('phone_number'))) {
        $form_state->setErrorByName('phone_number',
          $this->t("Невірний формат телефону! Номер телефону має бути в наступному форматі: +380(67)777-7-777"));
      }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
      if (!$form_state->get('id')) {
        $this->selectInDatabase($form, $form_state);
      } else {
        $this->updateInDatabase($form, $form_state);
      }
    }

    /**
     * Ajax callback to display errors, or add a comment and refresh the page.
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   An ajax response object.
     */
    public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
      $ajax_response = new AjaxResponse();
      $message = [
        '#theme' => 'status_messages',
        '#message_list' => drupal_get_messages(),
        '#status_headings' => [
          'error' => t('Error message'),
          'warning' => t('Warning message'),
        ],
      ];
      $messages = \Drupal::service('renderer')->render($message);

      if ($form_state->hasAnyErrors()) {
//        If there are errors of validation of the form we deduce them through Ajax
        $ajax_response->addCommand(new HtmlCommand('#form-system-messages', $messages));
      } else {
//        If the validation was successful, the following code will be executed.

//        Save the image permanently
        $this->setPhotoPermanent('avatar_photo', $form_state);
        $this->setPhotoPermanent('comment_image', $form_state);

//        We get the address of the current page and reload it.
        $url = Url::fromRoute('commentsbook.comments_page');
        $command = new RedirectCommand($url->toString());
        $ajax_response->addCommand($command);


        if (!$form_state->get('id')) {
          \Drupal::messenger()->addMessage(
            "Дякуємо за ваш коментар!",
            'status'
          );
        } else {
          \Drupal::messenger()->addMessage(
            "Коментар відредаговано!",
            'status'
          );
        }
      }

      return $ajax_response;
    }

    /**
     * Sets the uploaded images to status 1 in the table file_managed.
     *
     * @param $photoName
     * @param $form_state
     */
    public function setPhotoPermanent($photoName, $form_state) {
      $photoFid = $form_state->getValue($photoName);
      if(!empty($photoFid[0])) {
        $photoFid = $photoFid[0];
        $photo = \Drupal\file\Entity\File::load($photoFid);
        $photo->setPermanent();
        $photo->save();
      }
    }

    /**
     * Clears the comment text of unwanted characters.
     *
     * @param string $value
     *
     * @return string
     *    Clean text.
     */
    public function cleanFormText($value = "") {
      $value = trim($value);
      $value = stripslashes($value);
      $value = strip_tags($value);
      $value = htmlspecialchars($value);

      return $value;
    }

    /**
     * Method for writing a new comment to a database.
     *
     * @param $form
     * @param $form_state
     */
    public function selectInDatabase($form, $form_state) {
      $currentDate = date("H:i Y-m-d");

//      If the image was added, we get its name and write it to a variable. If not, set the default value.
      if (empty($form['avatar_photo']['#value']['fids']) == TRUE) {
        $avatar_photo = 'default_avatar.png';
      } else {
        $files = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->load($form_state->getValue('avatar_photo')[0]);
        $avatar_photo = $files->get('filename')->value;
      }

      if (empty($form['comment_image']['#value']['fids']) == TRUE) {
        $comment_image = NULL;
      } else {
        $files = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->load($form_state->getValue('comment_image')[0]);
        $comment_image = $files->get('filename')->value;
      }

//      Clear the comment text of unwanted characters
      $name = $this->cleanFormText($form_state->getValue('name'));
      $email = $this->cleanFormText($form_state->getValue('email'));
      $comment_text = $this->cleanFormText($form_state->getValue('comment_text'));

      $query = \Drupal::database()->insert('custom_comments');
      $query->fields([
        'name' => "{$name}",
        'email' => "{$email}",
        'phone_number' => "{$form_state->getValue('phone_number')}",
        'comment_text' => "{$comment_text}",
        'avatar_photo' => "{$avatar_photo}",
        'comment_image' => "{$comment_image}",
        'date' => "{$currentDate}",
      ]);
      $query->execute();
    }

    /**
     * Method for overwriting an edited comment.
     *
     * @param $form
     * @param $form_state
     */
    public function updateInDatabase($form, $form_state) {
//      If the image has been replaced / added, we get its name and write it to a variable. If not, set the value from the database.
      if (empty($form['avatar_photo']['#value']['fids']) == TRUE) {
        $avatar_photo = $form_state->get('avatar_photo');
      } else {
        $files = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->load($form_state->getValue('avatar_photo')[0]);
        $avatar_photo = $files->get('filename')->value;
      }

      if (empty($form['comment_image']['#value']['fids']) == TRUE) {
        $comment_image = $form_state->get('comment_image');
      } else {
        $files = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->load($form_state->getValue('comment_image')[0]);
        $comment_image = $files->get('filename')->value;
      }

//      Clear the comment text of unwanted characters
      $name = $this->cleanFormText($form_state->getValue('name'));
      $email = $this->cleanFormText($form_state->getValue('email'));
      $comment_text = $this->cleanFormText($form_state->getValue('comment_text'));

      $query = \Drupal::database()->update('custom_comments');
      $query->condition('id', $form_state->get('id'), '=');
      $query->fields([
        'name' => "{$name}",
        'email' => "{$email}",
        'phone_number' => "{$form_state->getValue('phone_number')}",
        'comment_text' => "{$comment_text}",
        'avatar_photo' => "{$avatar_photo}",
        'comment_image' => "{$comment_image}",
      ]);
      $query->execute();
    }

  }

