<?php

/*
    <Note to future self>

    Create "next page" to view the next videos

    Instead of embedding all videos on a page, create playlist and embed it
      - might require user permission?
      - still have as option?

    Don't embed all, display one at a time with a next video button?

    </End of note>
*/

// Require the google/apiclient library
//    $ composer require google/apiclient:~2.0
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
}
require_once __DIR__ . '/vendor/autoload.php';

// Create form to search for channel by name and input search criteria
$htmlBody = <<<END
<div class="container">
  <form id="form" class="form" method="GET">
    <div class="form-group">
      <h3> Search </h3>
      <input type="text" id="q" class="form-q" name="q" placeholder="Enter channel name" />
      <label for="order">Show me</label>
      <select class="form-select" name="order">
        <option value="viewcount">Most Viewed</option>
        <option value="rating">Top Rated</option>
      </select>
      <label for="timespan">From</label>
      <select class="form-select" name="timespan">
        <option value="day">Past 24 hours</option>
        <option value="week">Past week</option>
        <option value="month1">Past month</option>
        <option value="month3">Past 3 months</option>
        <option value="month6">Past 6 months</option>
        <option value="year">Past year</option>
        <option value="alltime">All time</option>
      </select>
      <input type="submit" id="submit" class="submit" value="Watch now" />
    </div>
  </form>
</div>
END;

// Executes after submission of search form, produces new results page
if (isset($_GET['q']) && isset($_GET['order']) && isset($_GET['timespan'])) {
  $DEVELOPER_KEY = 'AIzaSyBJ-4yJkvv1QKp3dnFBVoNWuXgquOMBrII';                   // REPLACE ME
  $client = new Google_Client();
  $client->setDeveloperKey($DEVELOPER_KEY);
  $youtube = new Google_Service_YouTube($client);

  $htmlBody = '';

  $order = $_GET['order'];
  $date = new DateTime;

  switch($_GET['timespan']) {
    case 'day':
      $date->sub(new DateInterval('P1D'));
      break;
    case 'week':
      $date->sub(new DateInterval('P7D'));
      break;
    case 'month1':
      $date->sub(new DateInterval('P1M'));
      break;
    case 'month3':
      $date->sub(new DateInterval('P3M'));
      break;
    case 'month6':
      $date->sub(new DateInterval('P6M'));
      break;
    case 'year':
      $date->sub(new DateInterval('P1Y'));
      break;
    default:
      $date = new DateTime("@0");
  }

  $dateStr = $date->format(DateTime::ATOM);

  try {
    // Retrieve info for channel matching search query
    $searchResponse = $youtube->search->listSearch('id,snippet', array(
      'q' => $_GET['q'],
      'maxResults' => 1,
      'type' => 'channel',
    ));

    // If no channel found (searchResponse returns empty)
    if (!$searchResponse['items']) {
      throw new Exception('Sorry, channel cannot be found :(');
    }

    $channelName = $searchResponse['items'][0]['snippet']['channelTitle'];
    $channelID = $searchResponse['items'][0]['snippet']['channelId'];

    $htmlBody .= "<h3>$channelName</h3>";

    // Retrieve all videos from channel matching the date parameters
    // Sort them by chosen order
    $searchResponse = $youtube->search->listSearch('id,snippet', array(
      'channelId' => $channelID,
      'maxResults' => 25,
      'type' => 'video',
      'publishedAfter' => $dateStr,
      'order' => $order,
    ));

    // Embeded them on page
    foreach ($searchResponse['items'] as $searchResult) {
        $videoID = $searchResult['id']['videoId'];
        $htmlBody .= '<iframe width="500" height="300" src="https://www.youtube.com/embed/'
                      . $videoID . '" frameborder="0" allowfullscreen> </iframe><br><br>';
    }

  // Catch exceptions
  } catch (Google_Service_Exception $e) {
    $htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
      htmlspecialchars($e->getMessage()));
  } catch (Google_Exception $e) {
    $htmlBody .= sprintf('<p>A client error occurred: <code>%s</code></p>',
      htmlspecialchars($e->getMessage()));
  } catch (Exception $e) {
      echo $e->getMessage(), "\n";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>YTsort</title>
    <link rel="stylesheet" href="css/style.css">
  </head>

  <body>
    <div class="main">

      <div class="header">
        <a href="index.php">
          <img src="images/logo.png" alt="YTsort">
        </a>
      </div>

      <?=$htmlBody?>

  </body>

</html>
