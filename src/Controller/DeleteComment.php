<?php


  namespace Drupal\commentsbook\Controller;

  use Drupal\Core\Controller\ControllerBase;

  /**
   * Class DeleteComment
   *
   * @package Drupal\commentsbook\Controller
   */
  class DeleteComment extends ControllerBase {

    /**
     * The method retrieves the comment id passed to it and removes the comment from the database with the corresponding id.
     * It then reloads the page and displays a successful removal message.
     *
     * @param int $id
     */
    public function delete($id = NULL) {

      $query = \Drupal::database()->delete('custom_comments');
      $query->condition('id', $id, '=');
      $query->execute();
      \Drupal::messenger()->addMessage("Коментар видалено!");

      header("Location: " . $_SERVER['HTTP_REFERER']);
      die();

    }

  }
