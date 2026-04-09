
<html>
<head>
<?php
session_start();
include("links.php");
?>
<style>
#searchbars { padding:5%; padding-top:10%; }
.search-wrap { display:flex; gap:8px; align-items:center; max-width:900px; }
.search-input {
  flex: 1;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 14px;
}
.search-input:focus {
  outline: none;
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14,165,233,0.15);
}
.search-small {
  color:#64748b;
  font-size:12px;
  margin-top:8px;
}
.search-clear-btn {
  border: 1px solid #ef4444;
  background: #fef2f2;
  color: #b91c1c;
  border-radius: 8px;
  padding: 10px 14px;
  cursor: pointer;
  font-weight: 600;
}
.search-clear-btn:hover {
  background: #fee2e2;
}
</style>
<script type="text/javascript">
var waecSearchTimer = null;
function SearchItem(forceNow){
  var input = document.getElementById("search_student");
  var holder = document.getElementById("search-student");
  if(!input || !holder){ return; }

  var str = (input.value || "").trim();
  if(str.length === 0){
    holder.innerHTML = "";
    return;
  }
  if(str.length < 2 && !forceNow){
    holder.innerHTML = "<div class='search-small'>Type at least 2 characters...</div>";
    return;
  }

  holder.innerHTML = "<div class='search-small'>Searching...</div>";
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function(){
    if(xhr.readyState === 4){
      if(xhr.status === 200){
        holder.innerHTML = xhr.responseText;
      } else {
        holder.innerHTML = "<div class='search-small' style='color:#b91c1c;'>Search failed. Try again.</div>";
      }
    }
  };
  xhr.open("GET", "display-student-items.php?search-item=" + encodeURIComponent(str), true);
  xhr.send();
}

function SearchItemDebounced(){
  if(waecSearchTimer){
    clearTimeout(waecSearchTimer);
  }
  waecSearchTimer = setTimeout(function(){ SearchItem(false); }, 280);
}

function ClearSearchItem(){
  var input = document.getElementById("search_student");
  var holder = document.getElementById("search-student");
  if(input){ input.value = ""; input.focus(); }
  if(holder){ holder.innerHTML = ""; }
}
</script>
<?php
include("links.php");
?>
</head>
<body>

  <div class="header">
    <!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
  <?php
  include("menu.php");

  ?>    
  <?php
  //include("side-menu.php");

  ?>
  </div>

<?php
//session_start();
if($_SESSION["SYSTEMTYPE"]=="Student")
{
}else{
?>
<div id="searchbars" style="padding:5%;padding-top:10%;">
<div class="search-wrap">
  <input class="search-input" type="text" id="search_student" name="search_student" placeholder="Type Index Number / Firstname / Othernames / Surname" oninput="SearchItemDebounced()" onkeydown="if(event.key==='Enter'){ event.preventDefault(); SearchItem(true);}"/>
  <button class="button-save" type="button" onclick="SearchItem(true)"><i class="fa fa-search"></i> Search</button>
  <button class="search-clear-btn" type="button" onclick="ClearSearchItem()">Clear</button>
</div>
<div class="search-small">Tip: start typing a few letters, then press Enter for an exact refresh.</div>

<div id="search-student" name="search-student"></div>
</div>
<?php
}
?>
</body>
</html>
