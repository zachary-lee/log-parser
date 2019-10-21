<?php

$longOptions  = array(
    "tags:",
);

$options = getopt('', $longOptions);

$tags = ['id', 'uri', 'msg'];
if (array_key_exists('tags', $options)) {
  $tags = explode(',', $options['tags']);
}

$file = $argv[$argc - 1];

if (strpos($file, 'error_log') === false) {
  die('This script only works on the Apache error_log');
} else if (! file_exists($file)) {
  die('Verify the error_log path is correct');
} else if (! $errorLog = file_get_contents($file)) {
  die('Unable to open the error_log file, or the file is empty');
}

$errorLogArray = explode("\n", $errorLog);

$output = [];
foreach ($errorLogArray as $errorLogLine) {
  if (strpos($errorLogLine, 'ModSecurity') === false) {
    continue;
  }

  foreach ($tags as $tag) {
    preg_match("/\[({$tag}) \"(.*?)\"]/", $errorLogLine, $matches);
    if ($matches && $matches[1] === $tag) {
      $output[$tag] = $matches[2];
    }
  }
}

foreach ($output as $key => $value) {
  echo "$value ";
}
