<?php

/**
 * Get the tags from the user passed options
 *
 * @param array $options The command line options
 *
 * @return array The user passed tags, or the defaults if none were provided
 */
function getTags ($options) {
  $tags = ['id', 'uri', 'msg']; //defaults
  if (array_key_exists('tags', $options)) {
    $tags = explode(',', $options['tags']);
  }

  return $tags;
}

/**
 * Check if the provided tags are in the list of acceptable tags
 *
 * @param array $tags The user provided tags
 *
 * @return bool True if all tags are valid, false otherwise
 */
function validTags ($tags) {
  $validTags =
      ['client', 'file', 'line', 'id', 'msg', 'data', 'severity', 'ver', 'tag', 'hostname', 'uri', 'unique_id'];

  //if the intersection of the arrays is smaller than the user tags array then there is an invalid tag
  return count(array_intersect($validTags, $tags)) === count($tags);
}

/**
 * Output the matching data
 *
 * @param array $matches An array of matching tag values from the error_log
 */
function outputModSecResults ($matches) {
  echo "Matching results from the error_log: " . PHP_EOL . PHP_EOL;
  foreach ($matches as $match) {
    foreach ($match as $tag => $value) {
      echo "$value ";
    }
    echo PHP_EOL;
  }
}

function outputCounts ($output) {
  //Sort of hacky way of flattening a multidimensional array
  $flatOutput = array_map('end', $output);

  $output_count = array_count_values($flatOutput);
  echo "Results of counting the total number of matching tags: " . PHP_EOL . PHP_EOL;
  foreach ($output_count as $key => $value) {
    echo "$key: $value " . PHP_EOL;
  }
}

///ideas: sort, count, prune duplicates (with counts), autoprepend args to URI, ignore msg
$longOptions = [
    "tags::",
];

$options = getopt('', $longOptions);

$tags = getTags($options);


if (!validTags($tags)) {
  die('You used an invalid tag');
}

//bug here when a file isn't given
$file = $argv[ $argc - 1 ];

if ($file !== $_SERVER['SCRIPT_FILENAME']) {
  if (strpos($file, 'error_log') === false) {
    die('This script only works on the Apache error_log');
  } else if (!file_exists($file)) {
    die('Verify the error_log path is correct');
  } else if (!$errorLog = file_get_contents($file)) {
    die('Unable to open the error_log file, or the file is empty');
  }
  $errorLogArray = explode("\n", $errorLog);
} else if (!$errorLogArray = file("php://stdin")) {
  die ('No log data provided');
}


$output = [];
foreach ($errorLogArray as $errorLogLine) {
  if (strpos($errorLogLine, 'ModSecurity') === false) {
    continue;
  }
  $matches_output = [];
  foreach ($tags as $tag) {
    preg_match("/\[({$tag}) \"(.*?)\"]/", $errorLogLine, $matches);
    if ($matches && $matches[1] === $tag) {
      $matches_output[] = $matches[2];
    }
  }
  $output[] = $matches_output;
}

if (true) { //sort, uniq, count argument(s)
  outputCounts($output);
}
outputModSecResults($output);
