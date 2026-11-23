<?php
require 'includes/bootstrap.php'; require_admin();
$view=($_GET['view']??'customers')==='collaborators'?'collaborators':'customers';
redirect('users.php?view='.$view);
