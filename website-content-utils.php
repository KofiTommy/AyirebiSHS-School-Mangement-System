<?php
/* Shared, database-backed content for the public school website. */
function website_content_defaults(){
    return array(
        'report_eyebrow' => 'Student notice',
        'report_title' => 'Need to print your',
        'report_title_emphasis' => 'terminal report?',
        'report_description' => 'Our ICT Centre has prepared a simple five-step guide to help students log in, find their terminal report and print it online.',
        'report_image' => 'images/websiteimages/How to Print your Report Online.jpeg',
        'report_button_label' => 'View the report guide',
        'phone' => '+233 (0) 24 560 5120',
        'email' => 'ayirebishs@ges.gov.gh',
        'address' => 'Ayirebi, Eastern Region|Ghana Post GPS: EM-0916-5753|P.O. Box 541, Akim Oda',
        'facebook_url' => 'https://www.facebook.com/Ayirebiseniorhighschool/',
        'tiktok_url' => 'https://www.tiktok.com/search?q=Official%20Ayisec%20Tv',
        'whatsapp_url' => 'https://wa.me/233245065954?text=Hello%2C%20I%20need%20help%20with%20Ayirebi%20Senior%20High%20School.'
    );
}
function website_content_ensure_table($con){
    static $done = false;
    if($done || !$con){ return; }
    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblwebsitecontent (content_key varchar(80) NOT NULL, content_value text NOT NULL, updated_by varchar(80) DEFAULT NULL, updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (content_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}
function website_content_all($con){
    $content = website_content_defaults();
    website_content_ensure_table($con);
    $result = @mysqli_query($con, "SELECT content_key, content_value FROM tblwebsitecontent");
    if($result){ while($row = mysqli_fetch_assoc($result)){ if(array_key_exists($row['content_key'], $content)){ $content[$row['content_key']] = $row['content_value']; } } }
    return $content;
}
function website_content_save($con, $content, $userId){
    website_content_ensure_table($con);
    $stmt = mysqli_prepare($con, "INSERT INTO tblwebsitecontent (content_key, content_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content_value=VALUES(content_value), updated_by=VALUES(updated_by)");
    if(!$stmt){ return false; }
    foreach($content as $key => $value){ mysqli_stmt_bind_param($stmt, 'sss', $key, $value, $userId); if(!mysqli_stmt_execute($stmt)){ mysqli_stmt_close($stmt); return false; } }
    mysqli_stmt_close($stmt); return true;
}
function website_content_escape($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function website_gallery_ensure_table($con){
    static $done = false;
    if($done || !$con){ return; }
    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblwebsitegallery (gallery_id int unsigned NOT NULL AUTO_INCREMENT, image_path varchar(255) NOT NULL, caption varchar(180) NOT NULL, uploaded_by varchar(80) DEFAULT NULL, uploaded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (gallery_id), UNIQUE KEY image_path (image_path)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

function website_gallery_items($con, $limit = 0){
    website_gallery_ensure_table($con); $items = array(); $paths = array();
    $result = @mysqli_query($con, "SELECT gallery_id, image_path, caption FROM tblwebsitegallery ORDER BY uploaded_at DESC, gallery_id DESC");
    if($result){ while($row = mysqli_fetch_assoc($result)){ if(is_file(__DIR__.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $row['image_path']))){ $items[] = array('id'=>(int)$row['gallery_id'], 'path'=>$row['image_path'], 'caption'=>$row['caption'], 'managed'=>true); $paths[$row['image_path']] = true; } } }
    $legacy = glob('images/websiteimages/*.{jpg,jpeg,png,JPG,JPEG,PNG,webp,WEBP}', GLOB_BRACE); sort($legacy, SORT_NATURAL | SORT_FLAG_CASE);
    foreach($legacy as $path){ if(!isset($paths[$path])){ $name = pathinfo($path, PATHINFO_FILENAME); $items[] = array('id'=>0, 'path'=>$path, 'caption'=>ucwords(str_replace(array('-', '_'), ' ', $name)), 'managed'=>false); } }
    return $limit > 0 ? array_slice($items, 0, $limit) : $items;
}

function website_gallery_delete($con, $id){
    website_gallery_ensure_table($con); $stmt = mysqli_prepare($con, "SELECT image_path FROM tblwebsitegallery WHERE gallery_id=? LIMIT 1");
    if(!$stmt){ return false; } mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); $result = mysqli_stmt_get_result($stmt); $row = $result ? mysqli_fetch_assoc($result) : null; mysqli_stmt_close($stmt);
    if(!$row){ return false; } $delete = mysqli_prepare($con, "DELETE FROM tblwebsitegallery WHERE gallery_id=?"); if(!$delete){ return false; } mysqli_stmt_bind_param($delete, 'i', $id); $ok = mysqli_stmt_execute($delete); mysqli_stmt_close($delete);
    if($ok && strpos($row['image_path'], 'uploads/website/gallery/') === 0){ @unlink(__DIR__.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $row['image_path'])); } return $ok;
}

function website_announcements_ensure_table($con){
    static $done = false;
    if($done || !$con){ return; }
    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblwebsiteannouncements (announcement_id int unsigned NOT NULL AUTO_INCREMENT, title varchar(180) NOT NULL, announcement_text text NOT NULL, status enum('published','hidden') NOT NULL DEFAULT 'published', published_by varchar(80) DEFAULT NULL, published_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (announcement_id), KEY status_published_at (status,published_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

function website_announcements($con, $includeHidden = false){
    website_announcements_ensure_table($con);
    $sql = "SELECT announcement_id, title, announcement_text, status, published_at FROM tblwebsiteannouncements".($includeHidden ? '' : " WHERE status='published'")." ORDER BY published_at DESC, announcement_id DESC";
    $items = array(); $result = @mysqli_query($con, $sql);
    if($result){ while($row = mysqli_fetch_assoc($result)){ $items[] = $row; } }
    return $items;
}
?>
