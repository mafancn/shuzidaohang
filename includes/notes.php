<?php
/* 后台记事本：多条笔记的增删改查、置顶与搜索。 */
if(!defined('IN_CRONLITE')) exit();

if(!defined('QIFU_NOTES_MAX_CONTENT_BYTES')) define('QIFU_NOTES_MAX_CONTENT_BYTES', 2000000);

function qifu_notes_ensure_table(){
    global $DB;
    static $ensured = false;
    if($ensured) return true;
    if($DB->get_row("SHOW TABLES LIKE 'web_notes'")){
        $ensured = true;
        return true;
    }
    if(defined('SQLITE')){
        $DB->query("CREATE TABLE IF NOT EXISTS web_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL DEFAULT '', content TEXT NOT NULL DEFAULT '', pinned INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL DEFAULT 0)");
    }else{
        $DB->query("CREATE TABLE IF NOT EXISTS `web_notes` (`id` int(11) NOT NULL AUTO_INCREMENT,`title` varchar(200) NOT NULL DEFAULT '',`content` mediumtext NOT NULL,`pinned` tinyint(1) NOT NULL DEFAULT 0,`created_at` int(11) NOT NULL DEFAULT 0,`updated_at` int(11) NOT NULL DEFAULT 0,PRIMARY KEY (`id`),KEY `pinned_updated` (`pinned`,`updated_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    $ensured = (bool)$DB->get_row("SHOW TABLES LIKE 'web_notes'");
    return $ensured;
}

function qifu_notes_preview_sql(){
    return defined('SQLITE') ? 'substr(content,1,240)' : 'LEFT(content,240)';
}

function qifu_notes_normalize($row, $with_content = false){
    if(!is_array($row)) return null;
    $note = array(
        'id'=>intval(isset($row['id']) ? $row['id'] : 0),
        'title'=>(string)(isset($row['title']) ? $row['title'] : ''),
        'pinned'=>intval(isset($row['pinned']) ? $row['pinned'] : 0) ? 1 : 0,
        'createdAt'=>intval(isset($row['created_at']) ? $row['created_at'] : 0),
        'updatedAt'=>intval(isset($row['updated_at']) ? $row['updated_at'] : 0)
    );
    if($with_content){
        $note['content'] = (string)(isset($row['content']) ? $row['content'] : '');
    }else{
        $note['preview'] = (string)(isset($row['preview']) ? $row['preview'] : '');
    }
    return $note;
}

function qifu_notes_clean_title($title, $content){
    $title = trim((string)$title);
    if($title === ''){
        $lines = preg_split('/\R/u', (string)$content, 2);
        $title = trim((string)($lines[0] ?? ''));
    }
    $title = preg_replace('/\s+/u', ' ', $title);
    $title = trim((string)$title);
    if(function_exists('mb_substr')){
        $title = mb_substr($title, 0, 80, 'UTF-8');
    }else{
        $title = substr($title, 0, 80);
    }
    return $title !== '' ? $title : '未命名笔记';
}

function qifu_notes_list($query = ''){
    global $DB;
    if(!qifu_notes_ensure_table()) return array('notes'=>array(), 'count'=>0);
    $query = trim((string)$query);
    if(function_exists('mb_substr')) $query = mb_substr($query, 0, 100, 'UTF-8');
    $preview = qifu_notes_preview_sql();
    $select = "SELECT id,title,pinned,created_at,updated_at,$preview AS preview FROM web_notes";
    if($query !== ''){
        $like = '%'.$query.'%';
        $rows = $DB->prepared_results($select.' WHERE title LIKE ? OR content LIKE ? ORDER BY pinned DESC, updated_at DESC, id DESC LIMIT 200', array($like, $like));
    }else{
        $rows = $DB->get_results($select.' ORDER BY pinned DESC, updated_at DESC, id DESC LIMIT 200');
    }
    $notes = array();
    foreach($rows as $row){
        $note = qifu_notes_normalize($row);
        if($note !== null) $notes[] = $note;
    }
    return array('notes'=>$notes, 'count'=>count($notes));
}

function qifu_notes_get($id){
    global $DB;
    $id = intval($id);
    if($id <= 0 || !qifu_notes_ensure_table()) return null;
    $row = $DB->prepared_row('SELECT id,title,content,pinned,created_at,updated_at FROM web_notes WHERE id=? LIMIT 1', array($id));
    return qifu_notes_normalize($row, true);
}

function qifu_notes_save($id, $title, $content, $pinned = 0){
    global $DB;
    if(!qifu_notes_ensure_table()) throw new RuntimeException('记事本数据表不可用');
    $id = intval($id);
    $content = (string)$content;
    if(strlen($content) > QIFU_NOTES_MAX_CONTENT_BYTES) throw new RuntimeException('笔记内容过长');
    $title = qifu_notes_clean_title($title, $content);
    if($title === '未命名笔记' && trim($content) === '') throw new RuntimeException('笔记内容不能为空');
    $pinned = intval($pinned) ? 1 : 0;
    $now = time();
    if($id > 0){
        $existing = $DB->prepared_row('SELECT id FROM web_notes WHERE id=? LIMIT 1', array($id));
        if(!$existing) throw new RuntimeException('笔记不存在');
        $DB->prepared_query('UPDATE web_notes SET title=?,content=?,pinned=?,updated_at=? WHERE id=?', array($title, $content, $pinned, $now, $id));
    }else{
        $id = intval($DB->prepared_insert('INSERT INTO web_notes (title,content,pinned,created_at,updated_at) VALUES (?,?,?,?,?)', array($title, $content, $pinned, $now, $now)));
        if($id <= 0) throw new RuntimeException('笔记保存失败');
    }
    return qifu_notes_get($id);
}

function qifu_notes_delete($id){
    global $DB;
    $id = intval($id);
    if($id <= 0 || !qifu_notes_ensure_table()) return false;
    $DB->prepared_query('DELETE FROM web_notes WHERE id=?', array($id));
    return true;
}

function qifu_notes_pin($id, $pinned){
    global $DB;
    $id = intval($id);
    if($id <= 0 || !qifu_notes_ensure_table()) return null;
    $DB->prepared_query('UPDATE web_notes SET pinned=? WHERE id=?', array(intval($pinned) ? 1 : 0, $id));
    return qifu_notes_get($id);
}
?>