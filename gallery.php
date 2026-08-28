<?php
require_once('dbstring.php');
require_once('website-content-utils.php');
$galleryItems = website_gallery_items($con);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Photo gallery of Ayirebi Senior High School in the Eastern Region of Ghana.">
  <title>Gallery | Ayirebi Senior High School</title>
  <link rel="icon" type="image/png" href="logo/logo-transparent.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="school-site.css">
  <link rel="stylesheet" href="css/ayisec-shared-palette.css">
</head>
<body>
  <div class="topbar"><div class="shell topbar-inner"><span>Eastern Region, Ghana</span><span class="top-links"><a href="website.php#contact">Contact us</a><a href="index.php#portal-login">Portal login</a></span></div></div>
  <header class="header"><div class="shell nav-wrap"><a class="brand" href="website.php" aria-label="Ayirebi Senior High School home"><span class="crest"><img src="logo/logo-transparent.png" alt="Ayirebi Senior High School logo"></span><span><strong>Ayirebi</strong><small>Senior High School</small></span></a><a class="nav-cta" href="website.php">Back to website</a></div></header>
  <main><section class="full-gallery" aria-labelledby="gallery-title"><div class="shell"><div class="gallery-title"><div><p class="eyebrow">The AYISEC gallery</p><h1 id="gallery-title">Our school<br><em>come alive.</em></h1></div><p>Explore the people, achievements, traditions, events and everyday life that make Ayirebi Senior High School special.</p></div><div class="gallery-wall"><?php foreach ($galleryItems as $galleryItem): $image = $galleryItem['path']; $label = trim((string)$galleryItem['caption']); if($label === ''){ $name = pathinfo($image, PATHINFO_FILENAME); $label = ucwords(str_replace(array('-', '_'), ' ', $name)); } ?><button class="gallery-item" type="button" data-image="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="Ayirebi Senior High School — <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy"><span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span></button><?php endforeach; ?></div></div></section></main>
  <footer><div class="shell footer-bottom"><span>© <?php echo date('Y'); ?> Ayirebi Senior High School</span><a href="website.php">Return to school website</a></div></footer>
  <script src="school-site.js"></script>
</body>
</html>
