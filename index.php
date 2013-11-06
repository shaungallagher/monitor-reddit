<?

// Your email address
$email = 'you@example.com';

// Only results from these subreddits will be reported
$subreddits = array('AskReddit', 'daddit', 'BabyBumps', 'Parenting', 'beyondthebump', 'science', 'askscience');

// Your database
$link = mysqli_connect("localhost", "username", "password", "database");

// Search query
$q = 'infants AND research';




// YOU NEED NOT EDIT BELOW THIS LINE

$url = 'http://www.reddit.com/search.json?q='.urlencode($q).'+nsfw%3Ano&restrict_sr=off&sort=new&t=day';
$json = json_decode(fetch_curl($url), true);
$body = '';

foreach ($json[data][children] as $key => $child) {

    $subreddit = $child[data][subreddit];
    if (in_array($subreddit, $subreddits)) {

        $permalink = $child[data][permalink];

        if ($stmt = mysqli_prepare($link, "SELECT permalink FROM reddit_results WHERE permalink = ?")) {

            mysqli_stmt_bind_param($stmt, "s", $permalink);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) == 0) {

                $created = date("Y-m-d h:i a", $child[data][created]);
                $int_created = intval($child[data][created]);

                $stmt2 = mysqli_prepare($link, "INSERT INTO reddit_results VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt2, "si", $permalink, $int_created);
                mysqli_stmt_execute($stmt2);

                $title = $child[data][title];
                $selftext = $child[data][selftext];
                $num_comments = $child[data][num_comments];

                $item = <<< EOF

<div class="item" style="border: 1px solid #CCC; margin: 10px; padding: 10px; font-family: arial; font-size: 13px">
    <h3 class="title" style="font-size: 16px; font-weight: bold">
        <a href="$permalink" style="color: #009; text-decoration: none" target="_new">$title</a> &nbsp;
        <a href="http://www.reddit.com/r/$subreddit" style="color: #163; text-decoration: none" target="_new">( r/$subreddit) </a>
    </h3>
    <p class="selftext" style="font-size: 13px">$selftext</p>
    <p style="color: #555; font-size: 12px">Number of comments: $num_comments</p>
    <p style="font-size: 13px">Created: $created</p>
</div>

EOF;

                $body .= $item;
                $results_count++;

            }
        }
    }
}

if ($results_count > 0) {
    mail($email, $results_count.' new results for query "'.$q.'" ('.date('h:i a').')', $body, "Content-type: text/html\nFrom: Reddit Search <$email>");
}

// Remove results that are more than 60 days old
$old_time = time()-5184000;
$stmt = mysqli_prepare($link, "DELETE FROM reddit_results WHERE created < ?");
mysqli_stmt_bind_param($stmt, "i", $old_time);
mysqli_stmt_execute($stmt);



function fetch_curl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    $source = curl_exec($ch);
    curl_close($ch);
    return $source;
}

