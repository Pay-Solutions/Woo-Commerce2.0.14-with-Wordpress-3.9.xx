<?php

include( 'wp-blog-header.php' );

 $refno = $_REQUEST['refno'];
 $merchantid = $_REQUEST['merchantid'];
 $total = $_REQUEST['total'];
 $customeremail = $_REQUEST['customeremail'];
$ref = '12';

$var = sprintf("%d", $refno); // octal representation

$wpdb->update(
    'wp_term_relationships',
    array(
         'term_taxonomy_id'   => $ref
      
    ),
    array( 'object_id' => $var ) 
); 

?>