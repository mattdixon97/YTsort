<?php

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
}
require_once __DIR__ . '/vendor/autoload.php';

// Create form to search for channel by name and input search criteria
$htmlBody = <<<END
<div class="container" id="search">
  <h3> Search </h3>
  <form id="form" class="form" method="GET">
    <div class="form-group">
      <input type="text" id="q" class="form-q" name="q" placeholder="Enter channel name" />
      <label for="order">Show me</label>
      <select class="form-select" name="order">
        <option value="viewcount">Most Viewed</option>
        <option value="rating">Top Rated</option>
      </select>
      <label for="timespan">From</label>
      <select class="form-select" name="timespan">
        <option value="past 24 hours">Past 24 hours</option>
        <option value="past week">Past week</option>
        <option value="past month">Past month</option>
        <option value="past 3 months">Past 3 months</option>
        <option value="past 6 months">Past 6 months</option>
        <option value="past year">Past year</option>
        <option value="all time">All time</option>
      </select>
      <input type="submit" id="submit" class="submit" value="Watch now" />
    </div>
  </form>
</div>
END;

// Executes after submission of search form, produces new results page
if (isset($_GET['q']) && isset($_GET['order']) && isset($_GET['timespan'])) {
  $DEVELOPER_KEY = 'REPLACE ME';

  $client = new Google_Client();
  $client->setDeveloperKey($DEVELOPER_KEY);
  $youtube = new Google_Service_YouTube($client);

  $htmlBody = '';

  $q = $_GET['q'];
  $order = $_GET['order'];
  $timespan = $_GET['timespan'];

  if (isset($_GET['page'])) {
    $page = $_GET['page'];
  } else {
    $page = 0;
  }

  if (isset($_GET['pageToken'])) {
    $pageToken = $_GET['pageToken'];
  } else {
    $pageToken = "";
  }

  switch($order) {
    case 'viewcount':
      $orderStr = "Most viewed";
      break;
    case 'rating':
      $orderStr = "Top rated";
      break;
  }

  $date = new DateTime;
  switch($timespan) {
    case 'past 24 hours':
      $date->sub(new DateInterval('P1D'));
      break;
    case 'past week':
      $date->sub(new DateInterval('P7D'));
      break;
    case 'past month':
      $date->sub(new DateInterval('P1M'));
      break;
    case 'past 3 months':
      $date->sub(new DateInterval('P3M'));
      break;
    case 'past 6 months':
      $date->sub(new DateInterval('P6M'));
      break;
    case 'past year':
      $date->sub(new DateInterval('P1Y'));
      break;
    default:
      $date = new DateTime("@0");
  }

  $dateStr = $date->format(DateTime::ATOM);

  try {

    // Retrieve info for channel matching search query
    $channelSearch = $youtube->search->listSearch('id,snippet', array(
      'q' => $q,
      'maxResults' => 1,
      'type' => 'channel',
    ));

    // If no channel found (searchResponse returns empty)
    if (!$channelSearch['items']) {
      throw new Exception('Sorry, channel cannot be found :(');
    }

    $channelName = $channelSearch['items'][0]['snippet']['channelTitle'];
    $channelID = $channelSearch['items'][0]['snippet']['channelId'];

    // Retrieve videos from channel matching the date parameters and sort
    // Only returns 25 videos at a time
    $videoSearch = $youtube->search->listSearch('id,snippet', array(
      'channelId' => $channelID,
      'maxResults' => 25,
      'type' => 'video',
      'publishedAfter' => $dateStr,
      'order' => $order,
      'pageToken' => $pageToken,
    ));

    // If no videos found (videoSearch returns empty)
    if (!$videoSearch['items']) {
      throw new Exception('Sorry, no videos available :(');
    }

    // Create layout to display results and embed the first video
    $htmlBody = '
                <div class="container" id="display">
                  <h3>'.$channelName.'</h3>
                  <div class="videoLayout">
                    <div id="video">
                      <iframe src="https://www.youtube.com/embed/'
                        . $videoSearch['items'][0]['id']['videoId']
                        . '" allowfullscreen>
                      </iframe>
                    </div>
                    <div>
                      <table class="videoList">
                        <caption>'.$orderStr.' from '.$timespan.'</caption>
                ';

    // Put all video results (up to 25) in a table
    // Change embeded video on page when table element is clicked
    $i = 1;
    foreach ($videoSearch['items'] as $searchResult) {
        $htmlBody .= '<tr onclick="document.getElementById(\'video\').innerHTML=
                      \'<iframe src=&quot;https://www.youtube.com/embed/'
                      . $searchResult['id']['videoId']. '&quot; allowfullscreen></iframe>\';">
                      <td>'.($i + (25 * $page)).'</td><td>'.$searchResult['snippet']['title'].'</td>
                      </tr>';
        $i++;
    }

    $htmlBody .= '    </table>
                    </div>
                  </div>
                  <div class="pageSelect">';

    // If there are >25 results, add links for next page/prev page when appropriate
    if (isset($videoSearch['prevPageToken'])) {
      $nextPage = $page - 1;
      $htmlBody .= '  <a class="next" href="index.php?q='.$q.'&order='.$order.
                        '&timespan='.$timespan.'&pageToken='.$videoSearch['prevPageToken']
                        .'&page='.($nextPage).'"> &#171; Back </a>';
    }

    if (isset($videoSearch['nextPageToken'])) {
      $prevPage = $page + 1;
      $htmlBody .= '  <a class="next" href="index.php?q='.$q.'&order='.$order.
                        '&timespan='.$timespan.'&pageToken='.$videoSearch['nextPageToken']
                        .'&page='.($prevPage).'"> More &#187; </a>';
    }

    $htmlBody .= '  </div>
                  </div>';

  // Catch exceptions
  } catch (Google_Service_Exception $e) {
    $htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
      htmlspecialchars($e->getMessage()));
  } catch (Google_Exception $e) {
    $htmlBody .= sprintf('<p>A client error occurred: <code>%s</code></p>',
      htmlspecialchars($e->getMessage()));
  } catch (Exception $e) {
      $htmlBody .= '<div class="error">
                      <h2>'. $e->getMessage() .'</h2>
                      <iframe width="560" height="315"
                        src="https://www.youtube.com/embed/nFAK8Vj62WM"
                        allowfullscreen>
                      </iframe>
                    </div>';
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
