<?php
$email = $_POST['email'];

// Load the existing XML file or create a new one if it doesn't exist
if (file_exists('email_list.xml')) {
    $doc = new DOMDocument();
    $doc->load('email_list.xml');
    $root = $doc->documentElement;
} else {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $root = $doc->createElement('emails');
    $doc->appendChild($root);
}

// Create a new email element and append it to the root
$emailElement = $doc->createElement('email');
$emailElement->appendChild($doc->createTextNode($email));
$root->appendChild($emailElement);

// Save the updated XML document
$doc->save('email_list.xml');

echo 'Email saved to XML file!';
?>