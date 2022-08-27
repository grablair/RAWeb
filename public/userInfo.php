<?php

use RA\ArticleType;
use RA\ClaimFilters;
use RA\ClaimSorting;
use RA\ClaimSpecial;
use RA\ClaimType;
use RA\Permissions;
use RA\Rank;
use RA\RankType;
use RA\UserAction;
use RA\UserRelationship;

$userPage = request('user');
if (empty($userPage) || !ctype_alnum($userPage)) {
    abort(404);
}

authenticateFromCookie($user, $permissions, $userDetails);

$maxNumGamesToFetch = requestInputSanitized('g', 5, 'integer');

getUserPageInfo($userPage, $userMassData, $maxNumGamesToFetch, 0, $user);
if (empty($userMassData)) {
    abort(404);
}

$userMotto = $userMassData['Motto'];
$userPageID = $userMassData['ID'];
$setRequestList = getUserRequestList($userPage);
$userSetRequestInformation = getUserRequestsInformation($userPage, $setRequestList);
$userWallActive = $userMassData['UserWallActive'];
$userIsUntracked = $userMassData['Untracked'];

// Get wall
$numArticleComments = getArticleComments(ArticleType::User, $userPageID, 0, 100, $commentData);

// Get user's feed
// $numFeedItems = getFeed( $userPage, 20, 0, $feedData, 0, 'individual' );

// Calc avg pcts:
$totalPctWon = 0.0;
$numGamesFound = 0;

$userCompletedGames = [];

// Get user's list of played games and pct completion
$userCompletedGamesList = getUsersCompletedGamesAndMax($userPage);
$userCompletedGamesListCount = count($userCompletedGamesList);

// Merge all elements of $userCompletedGamesList into one unique list
for ($i = 0; $i < $userCompletedGamesListCount; $i++) {
    $gameID = $userCompletedGamesList[$i]['GameID'];

    if ($userCompletedGamesList[$i]['HardcoreMode'] == 0) {
        $userCompletedGames[$gameID] = $userCompletedGamesList[$i];
    }

    $userCompletedGames[$gameID]['NumAwardedHC'] = 0; // Update this later, but fill in for now
}

for ($i = 0; $i < $userCompletedGamesListCount; $i++) {
    $gameID = $userCompletedGamesList[$i]['GameID'];
    if ($userCompletedGamesList[$i]['HardcoreMode'] == 1) {
        $userCompletedGames[$gameID]['NumAwardedHC'] = $userCompletedGamesList[$i]['NumAwarded'];
    }
}

// Custom sort, then overwrite $userCompletedGamesList
usort($userCompletedGames, fn ($a, $b) => ($b['PctWon'] ?? 0) <=> ($a['PctWon'] ?? 0));

$userCompletedGamesList = $userCompletedGames;

$excludedConsoles = ["Hubs", "Events"];

foreach ($userCompletedGamesList as $nextGame) {
    if ($nextGame['PctWon'] > 0) {
        if (!in_array($nextGame['ConsoleName'], $excludedConsoles)) {
            $totalPctWon += $nextGame['PctWon'];
            $numGamesFound++;
        }
    }
}

$avgPctWon = "0.0";
if ($numGamesFound > 0) {
    $avgPctWon = sprintf("%01.2f", ($totalPctWon / $numGamesFound) * 100.0);
}

sanitize_outputs(
    $userMotto,
    $userPage,
    $userMassData['RichPresenceMsg']
);

$pageTitle = "$userPage";

$daysRecentProgressToShow = 14; // fortnight

$userScoreData = getAwardedList(
    $userPage,
    0,
    $daysRecentProgressToShow,
    date("Y-m-d H:i:s", time() - 60 * 60 * 24 * $daysRecentProgressToShow),
    date("Y-m-d H:i:s", time())
);

// Get claim data if the user has jr dev or above permissions
if (getActiveClaimCount($userPage, true, true) > 0) {
    $userClaimData = getFilteredClaimData(0, ClaimFilters::Default, ClaimSorting::GameAscending, false, $userPage); // Active claims sorted by game title
}

// Also add current.
// $numScoreDataElements = count($userScoreData);
// $userScoreData[$numScoreDataElements]['Year'] = (int)date('Y');
// $userScoreData[$numScoreDataElements]['Month'] = (int)date('m');
// $userScoreData[$numScoreDataElements]['Day'] = (int)date('d');
// $userScoreData[$numScoreDataElements]['Date'] = date("Y-m-d H:i:s");
// $userScoreData[$numScoreDataElements]['Points'] = 0;
// settype($userPagePoints, 'integer');
// $userScoreData[$numScoreDataElements]['CumulScore'] = $userPagePoints;
//
// $pointsReverseCumul = $userPagePoints;
// for ($i = $numScoreDataElements; $i >= 0; $i--) {
//     $pointsReverseCumul -= $userScoreData[$i]['Points'];
//     $userScoreData[$i]['CumulScore'] = $pointsReverseCumul;
// }
//
// $numScoreDataElements++;

RenderOpenGraphMetadata(
    $userPage,
    "user",
    "/UserPic/$userPage" . ".png",
    "/user/$userPage",
    "$userPage Profile"
);
RenderContentStart($userPage);
?>
<script src="https://www.gstatic.com/charts/loader.js"></script>
<script>
  google.load('visualization', '1.0', { 'packages': ['corechart'] });
  google.setOnLoadCallback(drawCharts);

  function drawCharts() {
    var dataRecentProgress = new google.visualization.DataTable();

    // Declare columns
    dataRecentProgress.addColumn('date', 'Date');    // NOT date! this is non-continuous data
    dataRecentProgress.addColumn('number', 'Hardcore Score');
    dataRecentProgress.addColumn('number', 'Softcore Score');

    dataRecentProgress.addRows([
        <?php
        $arrayToUse = $userScoreData;

        $count = 0;
        foreach ($arrayToUse as $dayInfo) {
            if ($count++ > 0) {
                echo ", ";
            }

            $nextDay = (int) $dayInfo['Day'];
            $nextMonth = (int) $dayInfo['Month'] - 1;
            $nextYear = (int) $dayInfo['Year'];
            $nextDate = $dayInfo['Date'];

            $dateStr = getNiceDate(strtotime($nextDate), true);
            $hardcoreValue = $dayInfo['CumulHardcoreScore'];
            $softcoreValue = $dayInfo['CumulSoftcoreScore'];

            echo "[ {v:new Date($nextYear,$nextMonth,$nextDay), f:'$dateStr'}, $hardcoreValue, $softcoreValue ]";
        }
        ?>
    ]);

    var optionsRecentProcess = {
      backgroundColor: 'transparent',
      title: 'Recent Progress',
      titleTextStyle: { color: '#186DEE' },
      hAxis: { textStyle: { color: '#186DEE' }, slantedTextAngle: 90 },
      vAxis: { textStyle: { color: '#186DEE' } },
      legend: { position: 'none' },
      chartArea: { left: 42, width: 458, 'height': '100%' },
      showRowNumber: false,
      view: { columns: [0, 1] },
      colors: ['#186DEE','#8c8c8c'],
    };

    function resize() {
      chartRecentProgress = new google.visualization.AreaChart(document.getElementById('chart_recentprogress'));
      chartRecentProgress.draw(dataRecentProgress, optionsRecentProcess);
    }

    window.onload = resize();
    window.onresize = resize;
  }
</script>

<div id="mainpage">
    <div id="leftcontainer">
        <?php
        echo "<div class='navpath'>";
        echo "<a href='/userList.php'>All Users</a>";
        echo " &raquo; <b>$userPage</b>";
        echo "</div>";

        echo "<div class='usersummary'>";
        echo "<h3 class='longheader' >$userPage</h3>";
        echo "<img src='/UserPic/$userPage.png' alt='$userPage' align='right' width='128' height='128'>";

        if (isset($userMotto) && mb_strlen($userMotto) > 1) {
            echo "<div class='mottocontainer'>";
            echo "<span class='usermotto'>$userMotto</span>";
            echo "</div>";
        }

        if (isset($user) && ($user !== $userPage)) {
            echo "<div class='flex items-center gap-1'>";
            $friendshipType = GetFriendship($user, $userPage);
            switch ($friendshipType) {
                case UserRelationship::Following:
                    echo "<form class='inline-block' action='/request/user/update-relationship.php' method='post'>";
                    echo csrf_field();
                    echo "<input type='hidden' name='user' value='$userPage'>";
                    echo "<input type='hidden' name='action' value='" . UserRelationship::NotFollowing . "'>";
                    echo "<button class='btn btn-link'>Unfollow</button>";
                    echo "</form>";
                    break;
                case UserRelationship::NotFollowing:
                    echo "<form class='inline-block' action='/request/user/update-relationship.php' method='post'>";
                    echo csrf_field();
                    echo "<input type='hidden' name='user' value='$userPage'>";
                    echo "<input type='hidden' name='action' value='" . UserRelationship::Following . "'>";
                    echo "<button class='btn btn-link'>Follow</button>";
                    echo "</form>";
                    break;
            }

            if ($friendshipType != UserRelationship::Blocked) {
                echo "<form class='inline-block' action='/request/user/update-relationship.php' method='post'>";
                echo csrf_field();
                echo "<input type='hidden' name='user' value='$userPage'>";
                echo "<input type='hidden' name='action' value='" . UserRelationship::Blocked . "'>";
                echo "<button class='btn btn-link'>Block</button>";
                echo "</form>";
            } else {
                echo "<form class='inline-block' action='/request/user/update-relationship.php' method='post'>";
                echo csrf_field();
                echo "<input type='hidden' name='user' value='$userPage'>";
                echo "<input type='hidden' name='action' value='" . UserRelationship::NotFollowing . "'>";
                echo "<button class='btn btn-link'>Unblock</button>";
                echo "</form>";
            }
            echo "<a class='btn btn-link' href='/createmessage.php?t=$userPage'>Message</a>";

            if (GetFriendship($userPage, $user) == UserRelationship::Following) {
                echo "<span class='px-3'>Follows you</span>";
            }
            echo "</div>";
        }

        echo "<br>";

        $niceDateJoined = $userMassData['MemberSince'] ? getNiceDate(strtotime($userMassData['MemberSince'])) : null;
        if ($niceDateJoined) {
            echo "Member Since: $niceDateJoined<br>";
        }
        // LastLogin is updated on any activity -> "LastActivity"
        $niceDateLogin = $userMassData['LastActivity'] ? getNiceDate(strtotime($userMassData['LastActivity'])) : null;
        if ($niceDateLogin) {
            echo "Last Activity: $niceDateLogin<br>";
        }
        echo "Account Type: <b>[" . Permissions::toString($userMassData['Permissions']) . "]</b><br>";
        echo "<br>";

        $totalHardcorePoints = $userMassData['TotalPoints'];
        if ($totalHardcorePoints > 0) {
            $totalTruePoints = $userMassData['TotalTruePoints'];

            $retRatio = sprintf("%01.2f", $totalTruePoints / $totalHardcorePoints);
            echo "Hardcore Points: $totalHardcorePoints points<span class='TrueRatio'> ($totalTruePoints)</span></span><br>";

            echo "Site Rank: ";
            if ($userIsUntracked) {
                echo "<b>Untracked</b>";
            } elseif ($totalHardcorePoints < Rank::MIN_POINTS) {
                echo "<i>Needs at least " . Rank::MIN_POINTS . " points.</i>";
            } else {
                $countRankedUsers = countRankedUsers();
                $userRank = $userMassData['Rank'];
                $rankPct = sprintf("%1.2f", (($userRank / $countRankedUsers) * 100.0));
                $rankOffset = (int) (($userRank - 1) / 25) * 25;
                echo "<a href='/globalRanking.php?s=5&t=2&o=$rankOffset'>$userRank</a> / $countRankedUsers ranked users (Top $rankPct%)";
            }
            echo "<br>";

            echo "Retro Ratio: <span class='TrueRatio'><b>$retRatio</b></span><br>";
            echo "<br>";
        }

        $totalSoftcorePoints = $userMassData['TotalSoftcorePoints'];
        if ($totalSoftcorePoints > 0) {
            echo "Softcore Points: $totalSoftcorePoints points<br>";

            echo "Softcore Rank: ";
            if ($userIsUntracked) {
                echo "<b>Untracked</b>";
            } elseif ($totalSoftcorePoints < Rank::MIN_POINTS) {
                echo "<i>Needs at least " . Rank::MIN_POINTS . " points.</i>";
            } else {
                $countRankedUsers = countRankedUsers(RankType::Softcore);
                $userRankSoftcore = getUserRank($userPage, RankType::Softcore);
                $rankPct = sprintf("%1.2f", (($userRankSoftcore / $countRankedUsers) * 100.0));
                $rankOffset = (int) (($userRankSoftcore - 1) / 25) * 25;
                echo "<a href='/globalRanking.php?s=2&t=2&o=$rankOffset'>$userRankSoftcore</a> / $countRankedUsers ranked users (Top $rankPct%)";
            }
            echo "<br>";
            echo "<br>";
        }

        echo "Average Completion: <b>$avgPctWon%</b><br><br>";

        echo "<a href='/forumposthistory.php?u=$userPage'>Forum Post History</a>";
        echo "<br>";

        echo "<a href='/setRequestList.php?u=$userPage'>Requested Sets</a>"
            . " - " . $userSetRequestInformation['used']
            . " of " . $userSetRequestInformation['total'] . " Requests Made";
        echo "<br><br>";

        if (!empty($userMassData['RichPresenceMsg']) && $userMassData['RichPresenceMsg'] !== 'Unknown') {
            echo "<div class='mottocontainer'>Last seen ";
            if (!empty($userMassData['LastGameID'])) {
                $game = getGameData($userMassData['LastGameID']);
                echo ' in ' . GetGameAndTooltipDiv($game['ID'], $game['Title'], $game['ImageIcon'], null, false, 22) . '<br>';
            }
            echo "<code>" . $userMassData['RichPresenceMsg'] . "</code></div>";
        }

        $contribCount = $userMassData['ContribCount'];
        $contribYield = $userMassData['ContribYield'];
        if ($contribCount > 0) {
            echo "<strong>$userPage Developer Information:</strong><br>";
            echo "<a href='/gameList.php?d=$userPage'>View all achievements sets <b>$userPage</b> has worked on.</a><br>";
            echo "<a href='/individualdevstats.php?u=$userPage'>View  detailed stats for <b>$userPage</b>.</a><br>";
            echo "<a href='/claimlist.php?u=$userPage'>View claim information for <b>$userPage</b>.</a></br>";
            if (isset($user) && $permissions >= Permissions::Registered) {
                $openTicketsData = countOpenTicketsByDev($userPage);
                echo "<a href='/ticketmanager.php?u=$userPage'>Open Tickets: <b>" . array_sum($openTicketsData) . "</b></a><br>";
            }
            echo "Achievements won by others: <b>$contribCount</b><br>";
            echo "Points awarded to others: <b>$contribYield</b><br><br>";
        }

        // Display the users active claims
        if (isset($userClaimData) && (is_countable($userClaimData) ? count($userClaimData) : 0) > 0) {
            echo "<b>$userPage's</b> current claims:</br>";
            foreach ($userClaimData as $claim) {
                $details = "";
                $isCollab = $claim['ClaimType'] == ClaimType::Collaboration;
                $isSpecial = $claim['Special'] != ClaimSpecial::None;
                if ($isCollab) {
                    $details = " (" . ClaimType::toString(ClaimType::Collaboration) . ")";
                } else {
                    if (!$isSpecial) {
                        $details = "*";
                    }
                }
                echo GetGameAndTooltipDiv($claim['GameID'], $claim['GameTitle'], $claim['GameIcon'], $claim['ConsoleName'], false, 22) . $details . '<br>';
            }
            echo "* Counts against reservation limit</br></br>";
        }

        echo "</div>"; // usersummary

        if (isset($user) && $permissions >= Permissions::Admin) {
            echo "<div class='devbox mb-3'>";
            echo "<span onclick=\"$('#devboxcontent').toggle(); return false;\">Admin (Click to show):</span><br>";
            echo "<div id='devboxcontent' style='display: none'>";

            echo "<table cellspacing=8 border=1>";

            if ($permissions >= $userMassData['Permissions'] && ($user != $userPage)) {
                echo "<tr>";
                echo "<form method='post' action='/request/user/update.php'>";
                echo csrf_field();
                echo "<input type='hidden' name='property' value='" . UserAction::UpdatePermissions . "' />";
                echo "<input type='hidden' name='target' value='$userPage' />";
                echo "<td>";
                echo "<input type='submit' style='float: right;' value='Update Account Type' />";
                echo "</td><td>";
                echo "<select name='value' >";
                $i = Permissions::Banned;
                // Don't do this, looks weird when trying to change someone above you
                // while( $i <= $permissions && ( $i <= Permissions::Developer || $user == 'Scott' ) )
                while ($i <= $permissions) {
                    if ($userMassData['Permissions'] == $i) {
                        echo "<option value='$i' selected >($i): " . Permissions::toString($i) . " (current)</option>";
                    } else {
                        echo "<option value='$i'>($i): " . Permissions::toString($i) . "</option>";
                    }
                    $i++;
                }
                echo "</select>";

                echo "</td></form></tr>";
            }

            $newValue = $userIsUntracked ? 0 : 1;
            echo "<tr><td>";
            echo "<form method='post' action='/request/user/update.php'>";
            echo csrf_field();
            echo "<input type='hidden' name='property' value='" . UserAction::TrackedStatus . "' />";
            echo "<input type='hidden' name='target' value='$userPage' />";
            echo "<input type='hidden' name='value' value='$newValue' />";
            echo "<input type='submit' style='float: right;' value='Toggle Tracked Status' />";
            echo "</form>";
            echo "</td><td style='width: 100%'>";
            echo ($userIsUntracked == 1) ? "Untracked User" : "Tracked User";
            echo "</td></tr>";

            echo "<tr><td>";
            echo "<form method='post' action='/request/user/update.php'>";
            echo csrf_field();
            echo "<input type='hidden' name='property' value='" . UserAction::PatreonBadge . "' />";
            echo "<input type='hidden' name='target' value='$userPage' />";
            echo "<input type='hidden' name='value' value='0' />";
            echo "<input type='submit' style='float: right;' value='Toggle Patreon Supporter' />";
            echo "</form>";
            echo "</td><td>";
            echo HasPatreonBadge($userPage) ? "Patreon Supporter" : "Not a Patreon Supporter";
            echo "</td></tr>";

            echo "<tr><td>";
            echo "<form method='post' action='/request/user/recalculate-score.php'>";
            echo csrf_field();
            echo "<input type='hidden' name='u' value='$userPage' />";
            echo "<input type='submit' style='float: right;' value='Recalc Score Now' />";
            echo "</form>";
            echo "</td></tr>";

            echo "<tr><td>";
            echo "<form method='post' action='/request/user/remove-avatar.php' onsubmit='return confirm(\"Are you sure you want to permanently delete this avatar?\")'>";
            echo csrf_field();
            echo "<input type='hidden' name='u' value='$userPage' />";
            echo "<input type='submit' style='float: right;' value='Remove Avatar' />";
            echo "</form>";
            echo "</td></tr>";

            echo "<tr><td colspan=2>";
            echo "<div class='commentscomponent left'>";
            $numLogs = getArticleComments(ArticleType::UserModeration, $userPageID, 0, 1000, $logs);
            RenderCommentsComponent($user,
                $numLogs,
                $logs,
                $userPageID,
                ArticleType::UserModeration,
                $permissions
            );
            echo "</div>";
            echo "</td></tr>";

            echo "</table>";

            echo "</div>"; // devboxcontent

            echo "</div>"; // devbox
        }

        echo "<div class='userpage recentlyplayed' >";

        $recentlyPlayedCount = $userMassData['RecentlyPlayedCount'];

        echo "<h4>Last $recentlyPlayedCount games played:</h4>";
        for ($i = 0; $i < $recentlyPlayedCount; $i++) {
            $gameID = $userMassData['RecentlyPlayed'][$i]['GameID'];
            $consoleID = $userMassData['RecentlyPlayed'][$i]['ConsoleID'];
            $consoleName = $userMassData['RecentlyPlayed'][$i]['ConsoleName'];
            $gameTitle = $userMassData['RecentlyPlayed'][$i]['Title'];
            $gameLastPlayed = $userMassData['RecentlyPlayed'][$i]['LastPlayed'];

            sanitize_outputs($consoleName, $gameTitle);

            $pctAwarded = 100.0;

            if (isset($userMassData['Awarded'][$gameID])) {
                $numPossibleAchievements = $userMassData['Awarded'][$gameID]['NumPossibleAchievements'];
                $maxPossibleScore = $userMassData['Awarded'][$gameID]['PossibleScore'];
                $numAchieved = $userMassData['Awarded'][$gameID]['NumAchieved'];
                $scoreEarned = $userMassData['Awarded'][$gameID]['ScoreAchieved'];
                $numAchievedHardcore = $userMassData['Awarded'][$gameID]['NumAchievedHardcore'];
                $scoreEarnedHardcore = $userMassData['Awarded'][$gameID]['ScoreAchievedHardcore'];

                settype($numPossibleAchievements, "integer");
                settype($maxPossibleScore, "integer");
                settype($numAchieved, "integer");
                settype($scoreEarned, "integer");
                settype($numAchievedHardcore, "integer");
                settype($scoreEarnedHardcore, "integer");

                echo "<div class='userpagegames mb-3'>";

                RenderGameProgress($numPossibleAchievements, $numAchieved - $numAchievedHardcore, $numAchievedHardcore);

                echo "<a href='/game/$gameID'>$gameTitle ($consoleName)</a><br>";
                echo "Last played $gameLastPlayed<br>";

                if ($numPossibleAchievements) {
                    echo "Earned $numAchieved of $numPossibleAchievements achievements, ";
                    if ($scoreEarnedHardcore) {
                        echo "$scoreEarnedHardcore/$maxPossibleScore points";
                        if ($scoreEarned > $scoreEarnedHardcore) {
                            $scoreEarnedSoftcore = $scoreEarned - $scoreEarnedHardcore;
                            echo ", $scoreEarnedSoftcore softcore points";
                        }
                    } elseif ($scoreEarned) {
                        echo "$scoreEarned/$maxPossibleScore softcore points";
                    } else {
                        echo "0/$maxPossibleScore points";
                    }
                    echo ".<br/>";
                }

                if (isset($userMassData['RecentAchievements'][$gameID])) {
                    foreach ($userMassData['RecentAchievements'][$gameID] as $achID => $achData) {
                        $badgeName = $achData['BadgeName'];
                        $achID = $achData['ID'];
                        $achPoints = $achData['Points'];
                        $achTitle = $achData['Title'];
                        $achDesc = $achData['Description'];
                        $achUnlockDate = getNiceDate(strtotime($achData['DateAwarded']));
                        $achHardcore = $achData['HardcoreAchieved'];

                        $unlockedStr = "";
                        $class = 'badgeimglarge';

                        if (!$achData['IsAwarded']) {
                            $badgeName .= "_lock";
                        } else {
                            $unlockedStr = "<br clear=all>Unlocked: $achUnlockDate";
                            if ($achHardcore == 1) {
                                $unlockedStr .= "<br>-=HARDCORE=-";
                                $class = 'goldimage';
                            }
                        }

                        echo GetAchievementAndTooltipDiv(
                            $achID,
                            $achTitle,
                            $achDesc,
                            $achPoints,
                            $gameTitle,
                            $badgeName,
                            true,
                            true,
                            $unlockedStr,
                            48,
                            $class
                        );
                    }
                }

                echo "</div>";
            }
        }

        if ($maxNumGamesToFetch == 5 && $recentlyPlayedCount == 5) {
            echo "<div class='text-right mt-5'><a class='btn btn-link' href='/user/$userPage?g=15'>more...</a></div>";
        }

        echo "</div>"; // recentlyplayed

        echo "<div class='commentscomponent left'>";

        echo "<h4>User Wall</h4>";

        if ($userWallActive) {
            // passing 'null' for $user disables the ability to add comments
            RenderCommentsComponent(
                !isUserBlocking($userPage, $user) ? $user : null,
                $numArticleComments,
                $commentData,
                $userPageID,
                ArticleType::User,
                $permissions
            );
        } else {
            echo "<div class='leftfloat'>";
            echo "<i>This user has disabled comments.</i>";
            echo "</div>";
        }

        echo "</div>";
        ?>
    </div>
    <div id="rightcontainer">
        <?php
        RenderSiteAwards(getUsersSiteAwards($userPage));
        RenderCompletedGamesList($userCompletedGamesList);

        echo "<div id='achdistribution' class='component'>";
        echo "<h3>Recent Progress</h3>";
        echo "<div id='chart_recentprogress' class='mb-5'></div>";
        echo "<div class='text-right'><a class='btn btn-link' href='/history.php?u=$userPage'>more...</a></div>";
        echo "</div>";

        if ($user !== null && $user === $userPage) {
            RenderScoreLeaderboardComponent($user, true);
        }
        ?>
    </div>
</div>
<?php RenderContentEnd(); ?>
