<?php

namespace Hunter\simplenews\Controller;

use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response\JsonResponse;
use Fwolf\Wrapper\PHPMailer\PHPMailer;

/**
 * Class simplenews.
 *
 * @package Hunter\simplenews\Controller
 */
class SimplenewsController {
  /**
   * newsletter_list.
   *
   * @return string
   *   Return newsletter_list string.
   */
  public function newsletter_list(ServerRequest $request) {
    $parms = $request->getQueryParams();
    if(!isset($parms['page'])){
      $parms['page'] = 1;
    }
    $newsletter_result = get_all_newsletter($parms);

    return view('/admin/newsletter-list.html', array('newsletter' => $newsletter_result));
  }

  /**
   * subscription_list.
   *
   * @return string
   *   Return subscription_list string.
   */
  public function subscription_list(ServerRequest $request) {
    $parms = $request->getQueryParams();
    if(!isset($parms['page'])){
      $parms['page'] = 1;
    }
    $subscribe_result = get_all_subscription($parms);

    return view('/admin/subscribe-list.html', array('subscribe' => $subscribe_result));
  }

  /**
   * simplenews_add.
   *
   * @return string
   *   Return simplenews_add string.
   */
  public function newsletter_add(ServerRequest $request) {
    if ($parms = $request->getParsedBody()) {
      $user = session()->get('admin');

      $nid = db_insert('newsletter')
        ->fields(array(
          'title' => clean($parms['title']),
          'content' => clean($parms['content']),
          'status' => $parms['status'],
          'uid' => $user->uid,
          'created' => time(),
          'updated' => time(),
        ))
        ->execute();

      return hunter_form_submit($parms, 'simplenews', $nid);
    }

    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => '标题',
      '#maxlength' => 255,
    );
    $form['content'] = array(
      '#type' => 'textarea',
      '#title' => '内容',
      '#required' => TRUE,
      '#attributes' => array('id' => 'content', 'lay-verify' => 'content_content'),
    );
    $form['status'] = array(
      '#type' => 'radios',
      '#title' => '状态',
      '#default_value' => 'yes',
      '#options' => array(
        'yes' => '正常',
        'no' => '锁定',
      ),
    );
    $form['save'] = array(
     '#type' => 'submit',
     '#value' => t('Save'),
     '#attributes' => array('lay-submit' => '', 'lay-filter' => 'newsletterAdd'),
    );

    return view('/admin/newsletter-add.html', array('form' => $form));
  }

  /**
   * simplenews_edit.
   *
   * @return string
   *   Return simplenews_edit string.
   */
  public function newsletter_edit($nid) {
      $newsletter = get_newsletter_byid($nid);

      $form['title'] = array(
        '#type' => 'textfield',
        '#title' => '标题',
        '#default_value' => $newsletter->title,
        '#maxlength' => 255,
      );
      $form['content'] = array(
        '#type' => 'textarea',
        '#title' => '内容',
        '#default_value' => $newsletter->content,
        '#required' => TRUE,
        '#attributes' => array('id' => 'content', 'lay-verify' => 'content_content'),
      );
      $form['status'] = array(
        '#type' => 'radios',
        '#title' => '状态',
        '#default_value' => $newsletter->status ? $newsletter->status : 'yes',
        '#options' => array(
          'yes' => '正常',
          'no' => '锁定',
        ),
      );

      $form['nid'] = array(
        '#type' => 'hidden',
        '#value' => $nid,
      );
      $form['save'] = array(
       '#type' => 'submit',
       '#value' => t('Save'),
       '#attributes' => array('lay-submit' => '', 'lay-filter' => 'newsletterUpdate'),
      );

      return view('/admin/newsletter-edit.html', array('form' => $form, 'newsletter' => $newsletter, 'nid' => $nid));
  }

  /**
   * simplenews_update.
   *
   * @return string
   *   Return simplenews_update string.
   */
  public function newsletter_update(ServerRequest $request) {
    if ($parms = $request->getParsedBody()) {
        $user = session()->get('admin');

        db_update('newsletter')
          ->fields(array(
            'title' => clean($parms['title']),
            'content' => clean($parms['content']),
            'status' => $parms['status'],
            'uid' => $user->uid,
            'updated' => time(),
          ))
         ->condition('nid', $parms['nid'])
         ->execute();

        return hunter_form_submit($parms, 'simplenews', true);
     }
     return false;
  }

  /**
   * simplenews_del.
   *
   * @return string
   *   Return simplenews_del string.
   */
  public function newsletter_del($nid) {
    $result = db_delete('newsletter')
            ->condition('nid', $nid)
            ->execute();

    if ($result) {
      return true;
    }

    return false;
  }

  /**
   * newsletter_subscribe.
   *
   * @return string
   *   Return newsletter_subscribe string.
   */
  public function newsletter_subscribe(ServerRequest $request) {
    if ($parms = $request->getParsedBody()) {
      $user = session()->get('admin');
      $query_parms = $request->getQueryParams();

      $sid = db_insert('subscription')
        ->fields(array(
          'mail' => clean($parms['mail']),
          'status' => 'yes',
          'uid' => $user ? $user->uid : 0,
          'created' => time(),
        ))
        ->execute();

      if($sid){
        hunter_set_message('Thank you for subscribing.');
        if(isset($query_parms['redirect'])){
          return redirect($query_parms['redirect']);
        }
        return redirect('/');
      }
    }
  }

  /**
   * subscribe_email_check.
   *
   * @return string
   *   Return subscribe_email_check string.
   */
  public function subscribe_email_check(ServerRequest $request) {
    if ($parms = $request->getParsedBody()) {
      $subscription = subscription_load_by_mail($parms['email']);

      if (!empty($subscription)) {
        return new JsonResponse(array('code' => 1001, 'msg' => 'You are already subscribed to our newsletter.'));
      }
    }

    return new JsonResponse(array('code' => 0, 'msg' => 'success'));
  }

  /**
   * newsletter_send.
   *
   * @return string
   *   Return newsletter_send string.
   */
  public function newsletter_send(ServerRequest $request, PHPMailer $mail, $nid) {
    $newsletter = get_newsletter_byid($nid);
    $subscription = get_subscription_list();

    $body = $newsletter->content;
    $mail->isSMTP();
    $mail->SMTPSecure = 'tls';
    $mail->Host = variable_get('smtp_host');
    $mail->SMTPAuth = true;
    $mail->SMTPKeepAlive = true; // SMTP connection will not close after each email sent, reduces SMTP overhead
    $mail->Port = variable_get('smtp_port');
    $mail->Username = variable_get('smtp_username');
    $mail->Password = variable_get('smtp_password');
    $mail->setFrom(variable_get('smtp_username'), 'DrupalHunter');
    $mail->addReplyTo(variable_get('smtp_username'), 'DrupalHunter');
    $mail->Subject = $newsletter->title;
    //Same body for all messages, so set this before the sending loop
    //If you generate a different body for each recipient (e.g. you're using a templating system),
    //set it inside the loop
    $mail->msgHTML($body);
    //msgHTML also sets AltBody, but if you want a custom one, set it afterwards
    $mail->AltBody = 'To view the message, please use an HTML compatible email viewer!';
    $msg = '';

    foreach ($subscription as $sub) { //This iterator syntax only works in PHP 5.4+
        $mail->addAddress($sub->mail, get_username_byid($sub->uid));
        if (!$mail->send()) {
            $msg .= $sub->mail.' send error.<br />';
            break; //Abandon sending
        } else {
            $msg .= $sub->mail.' send success.<br />';
        }
        // Clear all addresses and attachments for next loop
        $mail->clearAddresses();
        $mail->clearAttachments();
    }

    return new JsonResponse(array('code' => 0, 'msg' => $msg));
  }

}
