<?php
function renderMessages($messages)
{
    foreach ($messages as $msg) {
        echo '<div class="msg">' . htmlspecialchars($msg) . '</div>';
    }
}
