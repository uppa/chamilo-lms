<?php
/* For licensing terms, see /license.txt */

/**
 * 	@package chamilo.forum
 *  @todo fix all this qualify files avoid including files, use classes POO jmontoya
 */

require_once '../inc/global.inc.php';
require_once 'forumconfig.inc.php';
require_once 'forumfunction.inc.php';

$nameTools = get_lang('ToolForum');
$this_section = SECTION_COURSES;

$message = '';
//are we in a lp ?
$origin = '';
if (isset($_GET['origin'])) {
    $origin = Security::remove_XSS($_GET['origin']);
}

$currentUserId = api_get_user_id();
$userIdToQualify = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
api_block_course_item_locked_by_gradebook($_GET['thread'], LINK_FORUM_THREAD);
$nameTools = get_lang('ToolForum');

$allowed_to_edit = api_is_allowed_to_edit(null, true);
$currentThread = get_thread_information($_GET['thread']);
$currentForum = get_forum_information($currentThread['forum_id']);

$allowToQualify = false;
if ($allowed_to_edit) {
    $allowToQualify = true;
} else {
    $allowToQualify = $currentThread['thread_peer_qualify'] == 1 && $currentForum['visibility'] == 1 && $userIdToQualify != $currentUserId;
}

if (!$allowToQualify) {
    api_not_allowed(true);
}

/*     Including necessary files */
$htmlHeadXtra[] = '<script>
    $(document).ready(function(){
        $(\'.hide-me\').slideUp()
    });

    function hidecontent(content){
        $(content).slideToggle(\'normal\');
    }
</script>';

$currentForumCategory = get_forumcategory_information(
    $currentForum['forum_category']
);
$groupId = api_get_group_id();

/*
    Header and Breadcrumbs
*/
if (isset($_SESSION['gradebook'])){
    $gradebook=	$_SESSION['gradebook'];
}

if (!empty($gradebook) && $gradebook=='view') {
    $interbreadcrumb[]= array (
        'url' => '../gradebook/'.$_SESSION['gradebook_dest'],
        'name' => get_lang('ToolGradebook')
    );
}

if ($origin == 'learnpath') {
    Display::display_reduced_header();
} else {
    if (!empty($groupId)) {
        $group_properties  = GroupManager::get_group_properties($groupId);
        $interbreadcrumb[] = array(
            "url" => "../group/group.php",
            "name" => get_lang('Groups'),
        );
        $interbreadcrumb[] = array(
            "url" => "../group/group_space.php?".api_get_cidreq(),
            "name"=> get_lang('GroupSpace').' ('.$group_properties['name'].')'
        );
        $interbreadcrumb[] = array(
            "url" => "viewforum.php?forum=".Security::remove_XSS($_GET['forum'])."&origin=".$origin."&search=".Security::remove_XSS(urlencode($_GET['search'])),
            "name" => prepare4display($currentForum['forum_title'])
        );
        if ($message <> 'PostDeletedSpecial') {
            $interbreadcrumb[]= array(
                "url" => "viewthread.php?forum=".Security::remove_XSS($_GET['forum'])."&gradebook=".$gradebook."&thread=".Security::remove_XSS($_GET['thread']),
                "name" => prepare4display($currentThread['thread_title'])
            );
        }

        $interbreadcrumb[] = array(
            "url" => "#",
            "name" => get_lang('QualifyThread'),
        );

        // the last element of the breadcrumb navigation is already set in interbreadcrumb, so give empty string
        Display :: display_header('');
        api_display_tool_title($nameTools);
    } else {

        $search = isset($_GET['search']) ? Security::remove_XSS(urlencode($_GET['search'])) : '';
        $info_thread = get_thread_information($_GET['thread']);
        $interbreadcrumb[] = array(
            "url" => "index.php?".api_get_cidreq()."&search=".$search,
            "name" => $nameTools);
        $interbreadcrumb[] = array(
            "url" => "viewforumcategory.php?forumcategory=".$currentForumCategory['cat_id']."&search=".$search,
            "name" => prepare4display($currentForumCategory['cat_title'])
        );
        $interbreadcrumb[] = array(
            "url" => "viewforum.php?forum=".Security::remove_XSS($_GET['forum'])."&origin=".$origin."&search=".$search,
            "name" => prepare4display($currentForum['forum_title'])
        );

        if ($message <> 'PostDeletedSpecial') {
            if (isset($_GET['gradebook']) and $_GET['gradebook']=='view') {
                $info_thread=get_thread_information(Security::remove_XSS($_GET['thread']));
                $interbreadcrumb[] = array(
                    "url" => "viewthread.php?".api_get_cidreq()."&forum=".$info_thread['forum_id']."&thread=".Security::remove_XSS($_GET['thread']),
                    "name" => prepare4display($currentThread['thread_title'])
                );
            } else {
                $interbreadcrumb[] = array(
                    "url" => "viewthread.php?".api_get_cidreq()."&forum=".Security::remove_XSS($_GET['forum'])."&thread=".Security::remove_XSS($_GET['thread']),
                    "name" => prepare4display($currentThread['thread_title'])
                );
            }
        }
        // the last element of the breadcrumb navigation is already set in interbreadcrumb, so give empty string
        $interbreadcrumb[]=array("url" => "#","name" => get_lang('QualifyThread'));
        Display :: display_header('');
    }
}

/*
    Actions
*/
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action =='delete' &&
    isset($_GET['content']) &&
    isset($_GET['id']) && api_is_allowed_to_edit(false, true)
) {
    $message = delete_post($_GET['id']);
}
if (($action == 'invisible' || $action == 'visible') &&
    isset($_GET['id']) && api_is_allowed_to_edit(false, true)
) {
    $message = approve_post($_GET['id'], $action);
}
if ($action == 'move' && isset($_GET['post'])) {
    $message = move_post_form();
}

/*
    Display the action messages
*/
if (!empty($message)) {
    Display :: display_confirmation_message(get_lang($message));
}

if ($allowToQualify) {
    $currentThread = get_thread_information($_GET['thread']);
    $threadId = $currentThread['thread_id'];
    // Show max qualify in my form
    $maxQualify = showQualify('2', $userIdToQualify, $threadId);

    $score = isset($_POST['idtextqualify']) ? $_POST['idtextqualify'] : '';

    if ($score > $maxQualify) {
        Display:: display_error_message(
            get_lang('QualificationCanNotBeGreaterThanMaxScore'),
            false
        );
    }

    if (!empty($score)) {
        $saveResult = saveThreadScore(
            $currentThread,
            $userIdToQualify,
            $threadId,
            $score,
            api_get_utc_datetime(),
            api_get_session_id()
        );
    }

    // show qualifications history
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $historyList = getThreadScoreHistory(
        $userIdToQualify,
        $threadId,
        $type
    );

    $counter = count($historyList);

    // Show current qualify in my form
    $qualify = current_qualify_of_thread(
        $threadId,
        api_get_session_id(),
        $_GET['user']
    );

    $result = get_statistical_information(
        $threadId,
        $_GET['user_id'],
        api_get_course_int_id()
    );

    $url = api_get_path(WEB_CODE_PATH).'forum/forumqualify.php?'.
            api_get_cidreq().'&forum='.intval($_GET['forum']).'&thread='.$threadId.'&user='.intval($_GET['user']).'&user_id='.intval($_GET['user']);

    $userToQualifyInfo = api_get_user_info($userIdToQualify);
    $form = new FormValidator('forum-thread-qualify', 'post', $url);
    $form->addHeader($userToQualifyInfo['complete_name']);
    $form->addLabel(get_lang('Thread'), $currentThread['thread_title']);
    $form->addLabel(get_lang('CourseUsers'), $result['user_course']);
    $form->addLabel(get_lang('PostsNumber'), $result['post']);
    $form->addLabel(get_lang('NumberOfPostsForThisUser'), $result['user_post']);
    $form->addLabel(
        get_lang('AveragePostPerUser'),
        round($result['user_post'] / $result['post'], 2)
    );
    $form->addText(
        'idtextqualify',
        array(get_lang('Qualification'), get_lang('MaxScore').' '.$maxQualify),
        $qualify
    );

    include 'viewpost.inc.php';

    $form->addButtonSave(get_lang('QualifyThisThread'));
    $form->setDefaults(array('idtextqualify' => $qualify));
    $form->display();

    // Show past data
    if (api_is_allowed_to_edit() && $counter > 0) {
        if (isset($_GET['gradebook'])){
            $view_gradebook='&gradebook=view';
        }
        echo '<h4>'.get_lang('QualificationChangesHistory').'</h4>';
        if (isset($_GET['type']) && $_GET['type'] == 'false') {
            $buttons = '<a class="btn btn-default" href="forumqualify.php?'.api_get_cidreq().'&forum='.intval($_GET['forum']).'&origin='.$origin.'&thread='.$threadId.'&user='.intval($_GET['user']).'&user_id='.intval($_GET['user_id']).'&type=true&idtextqualify='.$score.$view_gradebook.'#history">'.
                    get_lang('MoreRecent').'</a> <a class="btn btn-default disabled" >'.get_lang('Older').'</a>';
        } else {
            $buttons = '<a class="btn btn-default">'.get_lang('MoreRecent').'</a>
                        <a class="btn btn-default" href="forumqualify.php?'.api_get_cidreq().'&forum='.intval($_GET['forum']).'&origin='.$origin.'&thread='.$threadId.'&user='.intval($_GET['user']).'&user_id='.intval($_GET['user_id']).'&type=false&idtextqualify='.$score.$view_gradebook.'#history">'.
                    get_lang('Older').'</a>';
        }

        $table_list = '<br /><div class="btn-group">'.$buttons.'</div>';
        $table_list .= '<br /><table class="table">';
        $table_list .= '<tr>';
        $table_list .= '<th width="50%">'.get_lang('WhoChanged').'</th>';
        $table_list .= '<th width="10%">'.get_lang('NoteChanged').'</th>';
        $table_list .= '<th width="40%">'.get_lang('DateChanged').'</th>';
        $table_list .= '</tr>';

        for ($i = 0; $i < count($historyList); $i++) {
            $userInfo = api_get_user_info($historyList[$i]['qualify_user_id']);
            $table_list .= '<tr><td>'.$userInfo['complete_name'].'</td>';
            $table_list .= '<td>'.$historyList[$i]['qualify'].'</td>';
            $table_list .= '<td>'.api_convert_and_format_date(
                $historyList[$i]['qualify_time'],
                DATE_TIME_FORMAT_LONG
            );
            $table_list .= '</td></tr>';
        }
        $table_list.= '</table>';

        echo $table_list;
    }
}

if ($origin!='learnpath') {
    Display :: display_footer();
}
