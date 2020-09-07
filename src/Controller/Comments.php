<?php


  namespace Drupal\commentsbook\Controller;

  use \Drupal\Core\Controller\ControllerBase;

  /**
   * Class Comments
   *
   * @package Drupal\commentsbook\Controller
   */
  class Comments extends ControllerBase {

    /**
     * The method gets a form for adding comments and all comments from the database and displays them on the comments page.
     *
     * @return array
     */
    public function get_data() {

      Global $base_url;
      $comment_form = \Drupal::formBuilder()->getForm('Drupal\commentsbook\Form\MainForm');

      $comments = [];
      $query = \Drupal::database()->select('custom_comments', 'n');
      $query
        ->fields('n', ['id', 'name',  'phone_number', 'email', 'comment_text', 'avatar_photo', 'comment_image', 'date'])
        ->orderBy('id', 'DESC');
      $result = $query->execute()->fetchAll();

      foreach ($result as $comment) {
        array_push($comments, [
          'id' => $comment->id,
          'name' => $comment->name,
          'phone_number' => $comment->phone_number,
          'email' => $comment->email,
          'comment_text' => $comment->comment_text,
          'avatar_photo' => $comment->avatar_photo,
          'comment_image' => $comment->comment_image,
          'date' => $comment->date,
        ]);
      }

      $data = [
        'title' => 'Коментарі',
        'info' => $comments,
      ];

      return array(
        '#theme' => 'commentsbook_theme',
        '#data' => $data,
        '#comment_form' => $comment_form,
        '#base_url' => $base_url,
      );

    }

    /**
     * The method receives and displays a form for editing a comment on its id.
     *
     * @param int $id
     *
     * @return array
     */
    public function edit($id = NULL) {

      Global $base_url;
      $editcomment = \Drupal::formBuilder()->getForm('Drupal\commentsbook\Form\MainForm', $id);

      return array(
        '#theme' => 'commentsbook_edit_theme',
        '#title' => 'Редагувати коментар',
        '#edit_comment_form' => $editcomment,
        '#base_url' => $base_url,
      );

    }

  }
