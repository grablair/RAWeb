<?php

use RA\Permissions;
use RA\UserRelationship;

if (!authenticateFromCookie($user, $permissions, $userDetails, Permissions::Unregistered)) {
    abort(401);
}

$followingList = [];
$blockedUsersList = [];
foreach (GetExtendedFriendsList($user) as $entry) {
    switch ($entry['Friendship']) {
        case UserRelationship::Following:
            $followingList[] = $entry;
            break;
        case UserRelationship::Blocked:
            $blockedUsersList[] = $entry['User'];
            break;
    }
}
// GetExtendedFriendsList() returns most recent users first. sort by name for block list
asort($blockedUsersList);

$followersList = GetFollowers($user);

function RenderUserList(string $header, array $users, int $friendshipType, array $followingList)
{
    if (count($users) == 0) {
        return;
    }

    echo "<br/><h2>$header</h2>";
    echo "<table><tbody>";
    foreach ($users as $user) {
        echo "<tr>";

        echo "<td>";
        echo GetUserAndTooltipDiv($user, true);
        echo "</td>";

        echo "<td class='w-full'>";
        echo GetUserAndTooltipDiv($user);
        echo "</td>";

        echo "<td style='vertical-align:middle;'>";
        echo "<div class='flex justify-end gap-2'>";
        switch ($friendshipType) {
            case UserRelationship::Following:
                if (!array_search($user, array_column($followingList, 'User'))) {
                    echo "<form class='inline-block' action='/request/user/update-relationship.php' method='post'>";
                    echo csrf_field();
                    echo "<input type='hidden' name='user' value='$user'>";
                    echo "<input type='hidden' name='action' value='" . UserRelationship::Following . "'>";
                    echo "<button class='btn btn-link'>Follow</button>";
                    echo "</form>";
                }
                echo "<form class='inline-block' action='/request/user/update-relationship.php' method='post'>";
                echo csrf_field();
                echo "<input type='hidden' name='user' value='$user'>";
                echo "<input type='hidden' name='action' value='" . UserRelationship::Blocked . "'>";
                echo "<button class='btn btn-link'>Block</button>";
                echo "</form>";
                break;
            case UserRelationship::Blocked:
                echo "<form class='inline-block' action='/request/user/update-relationship.php' method='post'>";
                echo csrf_field();
                echo "<input type='hidden' name='user' value='$user'>";
                echo "<input type='hidden' name='action' value='" . UserRelationship::NotFollowing . "'>";
                echo "<button class='btn btn-link'>Unblock</button>";
                echo "</form>";
                break;
        }
        echo "</div>";
        echo "</td>";

        echo "</tr>";
    }
    echo "</tbody></table>";
}

RenderContentStart("Following");
?>
<div id="mainpage">
    <div id="fullcontainer">
        <h2>Following</h2>
        <?php
        if (empty($followingList)) {
            echo "You don't appear to be following anyone yet. Why not <a href='/userList.php'>browse the user pages</a> to find someone to add to follow?<br>";
        } else {
            echo "<table><tbody>";
            foreach ($followingList as $entry) {
                echo "<tr>";

                $followingUser = $entry['User'];

                echo "<td>";
                echo GetUserAndTooltipDiv($followingUser, true, null, 42);
                echo "</td>";

                echo "<td>";
                echo GetUserAndTooltipDiv($followingUser);
                echo "</td>";

                echo "<td class='w-full'>";
                if ($entry['LastActivityTimestamp']) {
                    echo '<div>Last seen ' . getNiceDate(strtotime($entry['LastActivityTimestamp'])) . '<div>';
                }
                if ($entry['LastGameID']) {
                    $gameData = getGameData($entry['LastGameID']);
                    echo '<div>';
                    echo '<small>';
                    echo GetGameAndTooltipDiv($gameData['ID'], $gameData['Title'], $gameData['ImageIcon'], $gameData['ConsoleName'], imgSizeOverride: 16);
                    echo '</small>';
                    echo '</div>';
                }

                echo '<div>';
                $activity = $entry['LastSeen'];
                sanitize_outputs($activity);
                echo '<small>' . $activity . '</small>';
                echo '</div>';
                echo "</td>";

                echo "<td style='vertical-align:middle;'>";
                echo "<div class='flex justify-end gap-2'>";

                echo "<a class='btn btn-link' href='/createmessage.php?t=$user'>Message</a>";

                echo "<form class='inline-block' action='/request/user/update-relationship.php' method='post'>";
                echo csrf_field();
                echo "<input type='hidden' name='user' value='$followingUser'>";
                echo "<input type='hidden' name='action' value='" . UserRelationship::NotFollowing . "'>";
                echo "<button class='btn btn-link'>Unfollow</button>";
                echo "</form>";

                echo "<form class='inline-block' action='/request/user/update-relationship.php' method='post'>";
                echo csrf_field();
                echo "<input type='hidden' name='user' value='$followingUser'>";
                echo "<input type='hidden' name='action' value='" . UserRelationship::Blocked . "'>";
                echo "<button class='btn btn-link'>Block</button>";
                echo "</form>";

                echo "</div>";
                echo "</td>";

                echo "</tr>";
            }
            echo "</tbody></table>";
        }

        RenderUserList('Followers', $followersList, UserRelationship::Following, $followingList);
        RenderUserList('Blocked', $blockedUsersList, UserRelationship::Blocked, $followingList);
        ?>
    </div>
</div>
<?php RenderContentEnd(); ?>
