#Amazon Cloud Player API for PHP

This is a work-in-progress class for accessing your http://amazon.com/cloudplayer account via PHP.  The class, running on your server, pretends to be a browser and interacts with your data as you would through the traditional UI.  Of course, being an API you can do a lot more cool stuff then what you are limited to in a browser.

Writting your own code, you could automatically generate a playlist of songs that are less than 30 seconds long or identify the tracks that were uploaded from your spouse's iTunes library.  You could also search for duplicate tracks or identify your imported MP3's that have been "upgraded" by Amazon.

Basic Usage to log in:
>
  <?php  
  session_start();  
  include_once 'acpapi.php';  
  $acp = new acp_api;  
  $acp->signin('my-username@example.com','my-amazon-password');  

Once Logged in, print out a list of your tracks:
>
  <?php  
  session_start();  
  include_once 'acpapi.php';  
  $acp = new acp_api;  
  $result = $acp->tracks();  
  print_r($acp->decode($result));  

###Requirements
PHP with the cURL library
