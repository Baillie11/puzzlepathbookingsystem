<?php
/**
 * Plugin Name: Puzzle Path Landing Catalogue
 * Description: Read-only catalogue adapter using the existing booking tables. Install only after approval.
 */
defined('ABSPATH') || exit;
add_action('rest_api_init',function(){
 register_rest_route('puzzlepath-landing/v1','/catalogue',[
  'methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(){
   global $wpdb;
   // Mirrors the booking form's actual eligibility predicate, including events without a hunt code.
   $rows=$wpdb->get_results("SELECT id,title,quest_description,location,price,seats FROM {$wpdb->prefix}pp_events WHERE seats > 0 AND display_on_site = 1 ORDER BY event_date ASC,id ASC");
   if ($wpdb->last_error) return new WP_Error('catalogue_unavailable','Quest catalogue temporarily unavailable.',['status'=>503]);
   $hunts=array_map(function($row){return ['id'=>(int)$row->id,'title'=>$row->title,'quest_description'=>wp_strip_all_tags($row->quest_description??''),'location'=>$row->location,'price'=>(float)$row->price,'seats_available'=>(int)$row->seats,'display_on_site'=>true];},$rows);
   return rest_ensure_response(['hunts'=>$hunts,'total_count'=>count($hunts)]);
  }
 ]);
});
