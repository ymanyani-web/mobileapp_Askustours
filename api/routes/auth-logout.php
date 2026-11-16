<?php
$u=user();db()->prepare('UPDATE sessions SET revoked_at=NOW() WHERE access_token_hash=?')->execute([hash('sha256',(string)token())]);out(['revoked'=>true]);
