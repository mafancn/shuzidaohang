<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

define('IN_CRONLITE', true);
define('SQLITE', true);
$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
require $root.'includes/db.class.php';
require $root.'includes/notes.php';

class NotesTestDB {
    public $pdo;
    function __construct(){
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    function get_row($q){
        if(preg_match("/^SHOW TABLES LIKE '([^']+)'/", trim($q), $m)){
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            $stmt->execute(array($m[1]));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : false;
        }
        $stmt = $this->pdo->query($q);
        return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    }
    function get_results($q){
        $stmt = $this->pdo->query($q);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }
    function query($q){
        $stmt = $this->pdo->query($q);
        return $stmt ? $stmt : false;
    }
    function prepared_query($q, $params = array()){
        $stmt = $this->pdo->prepare($q);
        return $stmt ? $stmt->execute($params) : false;
    }
    function prepared_row($q, $params = array()){
        $stmt = $this->pdo->prepare($q);
        if(!$stmt || !$stmt->execute($params)) return false;
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    }
    function prepared_results($q, $params = array()){
        $stmt = $this->pdo->prepare($q);
        if(!$stmt || !$stmt->execute($params)) return array();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    function prepared_insert($q, $params = array()){
        if(!$this->prepared_query($q, $params)) return false;
        return intval($this->pdo->lastInsertId());
    }
}

$failures = array();
function check_notes($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

$DB = new NotesTestDB();
check_notes(qifu_db_query_allowed("CREATE TABLE IF NOT EXISTS `web_notes` (`id` int(11) NOT NULL)"), 'web_notes creation is blocked by the database allowlist');
check_notes(!qifu_db_query_allowed('DROP TABLE web_notes'), 'destructive note table DDL is still allowed');
check_notes(qifu_notes_ensure_table(), 'notes table was not created');
check_notes(qifu_notes_list()['count'] === 0, 'empty notes list is not empty');

$created = qifu_notes_save(0, '', "第一条笔记标题\n正文包含关键词 alpha", 0);
check_notes(is_array($created) && $created['id'] > 0, 'new note was not created');
check_notes($created['title'] === '第一条笔记标题', 'blank title was not derived from the first line');
check_notes(strpos((string)$created['content'], '关键词 alpha') !== false, 'note content was not stored');
$note_id = intval($created['id']);

$listed = qifu_notes_list();
check_notes($listed['count'] === 1, 'notes list did not contain the new note');
check_notes(strpos((string)$listed['notes'][0]['preview'], '关键词') !== false, 'notes list preview is missing content');
check_notes(!isset($listed['notes'][0]['content']), 'notes list should not return full content');

$fetched = qifu_notes_get($note_id);
check_notes($fetched && strpos((string)$fetched['content'], '关键词 alpha') !== false, 'notes detail did not return full content');

$updated = qifu_notes_save($note_id, '资料合集', '更新后的正文 beta', 0);
check_notes($updated && $updated['title'] === '资料合集', 'note title was not updated');
check_notes($updated && strpos((string)$updated['content'], 'beta') !== false, 'note content was not updated');
check_notes($updated && $updated['updatedAt'] >= $created['updatedAt'], 'note updated time did not advance');

$pinned = qifu_notes_pin($note_id, 1);
check_notes($pinned && $pinned['pinned'] === 1, 'note was not pinned');
$listed = qifu_notes_list();
check_notes($listed['notes'][0]['id'] === $note_id && $listed['notes'][0]['pinned'] === 1, 'pinned note is not first');

$second = qifu_notes_save(0, '第二条', '包含搜索词 gamma', 0);
check_notes($second && $second['title'] === '第二条', 'second note was not created');
$found = qifu_notes_list('gamma');
check_notes($found['count'] === 1 && $found['notes'][0]['id'] === $second['id'], 'content search did not find the note');
$found_title = qifu_notes_list('资料合集');
check_notes($found_title['count'] === 1 && $found_title['notes'][0]['id'] === $note_id, 'title search did not find the note');

$threw = false;
try {
    qifu_notes_save(0, '', '   ', 0);
} catch (RuntimeException $error) {
    $threw = true;
}
check_notes($threw, 'empty note was accepted');

check_notes(qifu_notes_delete($note_id) === true, 'note delete failed');
check_notes(qifu_notes_get($note_id) === null, 'deleted note is still readable');
check_notes(qifu_notes_list()['count'] === 1, 'notes list count is wrong after delete');

if($failures){
    fwrite(STDERR, "Notes tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}
echo "Notes tests passed.\n";
?>