<?php
/*
Plugin Name: WPLMS User Generated Quiz
Plugin URI: http://www.Vibethemes.com
Description: Adds shortcode for mock quiz which user selects tags and creates quiz for himself .
Version: 1.1
Author: vibethemes,alexhal
Author URI: http://www.vibethemes.com
License: GPL2
*/
/*
Copyright 2014  VibeThemes  (email : vibethemes@gmail.com)

WPLMS User Generated Quiz program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

WPLMS User Generated Quiz program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WPLMS User Generated Quiz program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if(!class_exists('Wplms_Umq'))
{   
    class Wplms_Umq  // We'll use this just to avoid function name conflicts 
    {
      public static $instance;
        public static function init(){
          if ( is_null( self::$instance ) )
              self::$instance = new Wplms_Umq();
          return self::$instance;
      }
      function __construct(){
        $this->quiz_questions = array();
        add_action('wp_ajax_user_quiz_form_submit',array($this,'user_quiz_form_submit'));
        add_shortcode('user_dynamic_quiz',array($this,'render_form_user_dynamic_quiz'));
        add_action('wplms_quiz_retake',array($this,'bypass_retake'),10,2);
        add_action('wplms_submit_quiz',array($this,'remove_mock_data'),10,2);
        add_action('plugins_loaded',array($this,'wplms_umq_translations'));
      }

      function remove_mock_data($quiz_id,$user_id){
        delete_user_meta($user_id,'quiz_questions_mock'.$quiz_id);
      }

      function bypass_retake($quiz_id,$user_id){
          if(function_exists('bp_course_update_quiz_questions')){
            $quiz_questions = get_user_meta($user_id,'quiz_questions_mock'.$quiz_id,true);
            if(!empty($quiz_questions)){
              bp_course_update_quiz_questions($quiz_id,$user_id,$quiz_questions);
              if(function_exists('bp_course_update_user_quiz_status')){
                bp_course_update_user_quiz_status($user_id,$quiz_id,2);
              }
            }
          }
      }
      function user_quiz_form_submit(){
        if ( empty($_POST['security']) || empty($_POST['quiz_id']) || !wp_verify_nonce($_POST['security'],'user_quiz_form'.$_POST['quiz_id']) || !is_user_logged_in() ){
          die();
        }else{
          $user_id = get_current_user_id();
          $quiz_id = (is_numeric($_POST['quiz_id'])?$_POST['quiz_id']:'');
          if(empty($quiz_id)){
            die();
          }
          $data = json_decode(stripslashes($_POST['data']));
          $quiz_questions = array();
          foreach ($data as $key => $question) {
             if($question->questions && !empty($question->marks)){
                $args = array(
                      'post_type' => 'question',
                      'orderby' => 'rand', 
                      'posts_per_page' => $question->questions,
                      'tax_query' => array(
                          array(
                            'taxonomy' => 'question-tag',
                            'field' => 'id',
                            'terms' => array($question->tag),
                            'operator' => 'IN'
                          ),
                      )
                );
                $the_query = new WP_Query( $args );
                if($the_query->have_posts()){
                  while ( $the_query->have_posts() ) {
                      $the_query->the_post();
                      if(empty($quiz_questions['ques'])){
                        $quiz_questions['ques'][]=get_the_ID();
                        $quiz_questions['marks'][]=$question->marks;
                      }elseif(!in_array(get_the_ID(),$quiz_questions['ques'])){
                        $quiz_questions['ques'][]=get_the_ID();
                        $quiz_questions['marks'][]=$question->marks;
                      }
                  }
                }
                wp_reset_postdata();
            }
             
          }
          print_r($quiz_questions);
          if(!empty($quiz_questions) && !empty($quiz_questions['ques']) && count($quiz_questions['ques'])){
            if(function_exists('bp_course_update_quiz_questions')){
              bp_course_update_quiz_questions($quiz_id,$user_id,$quiz_questions);
              update_user_meta($user_id,'quiz_questions_mock'.$quiz_id,$quiz_questions);

              $this->quiz_questions = $quiz_questions;
            }
            if(function_exists('bp_course_update_user_quiz_status')){
              bp_course_update_user_quiz_status($user_id,$quiz_id,2);
            }
            
            
          }
        }
        die();
      }


      function render_form_user_dynamic_quiz($atts,$content=null){
          extract(shortcode_atts(array(
                  'questions'   => '',
                  'marks'   => '',
                  ), $atts));
          if(!is_user_logged_in())
            return;
          global $post;
          $user_id = get_current_user_id();
          $status = get_user_meta($user_id,'quiz_questions_mock'.$post->ID,$quiz_questions);
          if(!empty($status) && count($status) > 0)
            return;
          $terms = get_terms('question-tag');
          $terms_array  = array();
          if(!empty($terms)){
            foreach($terms as $term){
              $terms_array[$term->term_id] = array('id'=>$term->term_id,'name'=>$term->name,'count'=>$term->count);
            }
          }
          ob_start();    
          ?>
          <style type="text/css">
            .user_quiz_form label.label_heading:first-child{margin-right:120px;}
            span.quiz_field {min-width: 120px;display: inline-block;text-align: center;}
            .user_quiz_form label.label_heading {margin:10px;}
            .label_wrapper{clear:both;float:none;display: block;}
            span.remove_quiz_tag{margin-left:180px;color:red;cursor:pointer;}
            .all_quiz_tags_feilds:first span.remove_quiz_tag{display: none;}
          </style>
          <script>
            jQuery(document).ready(function($){
              jQuery('.user_quiz_form').each(function(){
                var $this = $(this);
                $('.add_more_question_tag').click(function(){
                  var cloned = $this.find('.quiz_tags_field_wrapper:first').clone();
                  $this.find('.all_quiz_tags_feilds').append(cloned);
                  cloned.append('<span class="remove_quiz_tag fa fa-times"></span>');
                });

                $('.user_quiz_form_submit').click(function(e){
                  e.preventDefault();
                  e.stopPropagation();
                  var quiz_tags_json = [];
                  var defaultext = $('.user_quiz_form_submit').text();
                  $('.user_quiz_form_submit').text("<?php echo _x('Processing...','','wplms-umq');?>");
                  $('.quiz_tags_field_wrapper').each(function(){
                    var $fields = $(this);
                    var tag = $fields.find('select.quiz_tag').val();
                    var questions = $fields.find('.question_number').val();
                    var marks = $fields.find('.quiz_marks').val();
                    var x = {'tag':tag,'questions':questions,'marks':marks};
                    quiz_tags_json.push(x);
                  });
                  $.ajax({
                         type: "POST",
                          url: ajaxurl,
                          data: { action: 'user_quiz_form_submit', 
                                  security: $('#user_quiz_form').val(),
                                  data : JSON.stringify(quiz_tags_json),
                                  quiz_id : <?php echo $post->ID;?>
                                },
                          cache: false,
                          success: function (html) {
                             if(html !=='' || html != '0'){
                               location.reload();
                             }
                          }
                  });
                });
                $('body').delegate('.remove_quiz_tag','click',function(){
                  var $this = $(this);
                  $this.parent().remove();
                });

              });
            });
          </script>
          <div class="user_quiz_form">
          <?php wp_nonce_field('user_quiz_form'.$post->ID,'user_quiz_form');?>
            <a class="add_more_question_tag button"><?php echo _x('Add more Tags','','wplms-umq');?></a>
            <div class="label_wrapper">
              <label class="label_heading"><?php echo _x('Tag','','wplms-umq');?></label>
              <label class="label_heading"><?php echo _x('Number of question','','wplms-umq');?></label>
              <label class="label_heading"><?php echo _x('Marks per question','','wplms-umq');?></label>
            </div>
            <div class="all_quiz_tags_feilds">
              <div class="quiz_tags_field_wrapper">
                <select name="quiz_tag" class="quiz_tag quiz_field">
                  <?php
                    if(!empty($terms_array)){
                      foreach($terms_array as $term){
                        echo '<option value="'.$term['id'].'">'.$term['name'].' ('.$term['count'].') </option>';
                      }
                    }
                  ?>
                </select>

                <?php 
                  if(empty($questions)){
                    echo '<input type="number" name="question_number" class="question_number quiz_field">';
                  }elseif(is_numeric($questions)){
                    echo '<input type="hidden" disabled name="quiz_marks" class="question_number quiz_field" value="'.$questions.'"><span class="quiz_field">'.$questions.'</span>';
                  }else{
                    echo '<input type="number" name="question_number" class="question_number quiz_field">';
                  }
                ?>
               
                <?php 
                  if(empty($marks)){
                    echo '<input type="number" name="quiz_marks" class="quiz_marks quiz_field">';
                  }elseif(is_numeric($marks)){
                    echo '<input type="hidden" disabled name="quiz_marks" class="quiz_marks quiz_field" value="'.$marks.'"><span class="quiz_field">'.$marks.'</span>';
                  }else{
                    echo '<input type="number" name="quiz_marks" class="quiz_marks quiz_field">';
                  }
                ?>
              </div>
            </div>
            <a  type="submit" class="button user_quiz_form_submit"><?php echo _x('Submit it','','wplms-umq');?></a>
          </div>
          <?php
          $form .= ob_get_contents();
          ob_get_clean();
          return $form;
      }

      function wplms_umq_translations(){
          $locale = apply_filters("plugin_locale", get_locale(), 'wplms-umq');
          $lang_dir = dirname( __FILE__ ) . '/languages/';
          $mofile        = sprintf( '%1$s-%2$s.mo', 'wplms-umq', $locale );
          $mofile_local  = $lang_dir . $mofile;
          $mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;

          if ( file_exists( $mofile_global ) ) {
              load_textdomain( 'wplms-umq', $mofile_global );
          } else {
              load_textdomain( 'wplms-umq', $mofile_local );
          }  
      }

    }
}

add_action('init',function(){
  if ( in_array( 'vibe-customtypes/vibe-customtypes.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && in_array( 'vibe-course-module/loader.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
  $umq = Wplms_Umq::init();
  }
});





