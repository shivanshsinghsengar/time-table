<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /'); exit; }

$schoolName     = htmlspecialchars(trim($_POST['school_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$schoolStart    = $_POST['school_start']    ?? '08:00';
$schoolEnd      = $_POST['school_end']      ?? '14:00';
$periodDuration = (int)($_POST['period_duration'] ?? 45);
$lunchStart     = $_POST['lunch_start']     ?? '11:00';
$lunchEnd       = $_POST['lunch_end']       ?? '11:30';
$daysPerWeek    = (int)($_POST['days_per_week'] ?? 5);
$classNames     = array_values(array_filter(array_map('trim', $_POST['classes']  ?? [])));
$subjectNames   = array_values(array_filter(array_map('trim', $_POST['subjects'] ?? [])));
$teacherNames   = array_values(array_filter(array_map('trim', $_POST['teachers'] ?? [])));
$teacherSubjects= array_map('trim', $_POST['teacher_subjects']   ?? []);
$teacherLunchS  = $_POST['teacher_lunch_start'] ?? [];
$teacherLunchE  = $_POST['teacher_lunch_end']   ?? [];
$allowFree      = ($_POST['allow_free']    ?? 'no') === 'yes';
$freePerDay     = (int)($_POST['free_per_day']   ?? 1);
$freePosition   = $_POST['free_position'] ?? 'end';

if (empty($classNames)||empty($subjectNames)||empty($teacherNames))
    die("<p style='color:#dc2626;font-family:sans-serif;padding:40px;'>Please fill in classes, subjects, and teachers.</p>");

function toMins(string $t): int { [$h,$m]=explode(':',$t); return (int)$h*60+(int)$m; }
function toTime(int $m): string { return sprintf('%02d:%02d',intdiv($m,60),$m%60); }

function buildSlots(string $s,string $e,int $d,string $lS,string $lE): array {
    $slots=[];$cur=toMins($s);$end=toMins($e);$lSm=toMins($lS);$lEm=toMins($lE);$ld=false;
    while($cur<$end){
        if(!$ld&&$cur>=$lSm){$slots[]=['start'=>toTime($lSm),'end'=>toTime($lEm),'is_lunch'=>true];$cur=$lEm;$ld=true;continue;}
        $se=$cur+$d; if(!$ld&&$se>$lSm)$se=$lSm; if($se>$end)break;
        $slots[]=['start'=>toTime($cur),'end'=>toTime($se),'is_lunch'=>false];$cur=$se;
    }
    return $slots;
}

$days  = $daysPerWeek===6?['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']:['Monday','Tuesday','Wednesday','Thursday','Friday'];
$slots = buildSlots($schoolStart,$schoolEnd,$periodDuration,$lunchStart,$lunchEnd);
$slotsPerDay         = count(array_filter($slots,fn($s)=>!$s['is_lunch']));
$teachingSlotsPerDay = $allowFree?max(1,$slotsPerDay-$freePerDay):$slotsPerDay;
$totalWeekSlots      = $teachingSlotsPerDay*$daysPerWeek;

$subjects=[]; foreach($subjectNames as $n){if($n!=='')$subjects[]=['name'=>$n];}
$numSubjects  = count($subjects);
$basePerWeek  = $numSubjects>0?intdiv($totalWeekSlots,$numSubjects):0;
$extraSubjects= $numSubjects>0?$totalWeekSlots%$numSubjects:0;
foreach($subjects as $si=>&$sub) $sub['periods_per_week']=$basePerWeek+($si<$extraSubjects?1:0);
unset($sub);

$teachers=[];
foreach($teacherNames as $i=>$n){
    if($n==='')continue;
    $teachers[]=['name'=>$n,'subject'=>$subjectNames[$i]??'','lunch_start'=>$teacherLunchS[$i]??$lunchStart,'lunch_end'=>$teacherLunchE[$i]??$lunchEnd];
}

$subjectTeacherMap=[];
foreach($subjects as $si=>$sub) $subjectTeacherMap[$si]=$si;

function teacherOnLunch(array $t,array $slot):bool{
    return toMins($slot['start'])<toMins($t['lunch_end'])&&toMins($slot['end'])>toMins($t['lunch_start']);
}

function getFreeSlotIndices(array $idx,int $n,string $pos):array{
    $c=min($n,count($idx)); if($c===0)return[];
    if($pos==='start')return array_slice($idx,0,$c);
    if($pos==='end')return array_slice($idx,-$c);
    $total=count($idx);$step=(int)floor($total/($c+1));$picks=[];
    for($i=1;$i<=$c;$i++)$picks[]=$idx[min($i*$step,$total-1)];
    return $picks;
}

function generateTimetable(array $classes,array $subjects,array $teachers,array $stm,array $days,array $slots,bool $af,int $fpd,string $fp):array{
    $nc=count($classes);$ns=count($subjects);
    $quota=[];for($ci=0;$ci<$nc;$ci++)for($si=0;$si<$ns;$si++)$quota[$ci][$si]=$subjects[$si]['periods_per_week'];
    $booked=[];$tt=[];
    $nlIdx=array_keys(array_filter($slots,fn($s)=>!$s['is_lunch']));
    $freeIdx=$af?getFreeSlotIndices($nlIdx,$fpd,$fp):[];
    foreach($days as $day){
        for($ci=0;$ci<$nc;$ci++){
            $used=[];
            foreach($slots as $sIdx=>$slot){
                if($slot['is_lunch']){$tt[$ci][$day][$sIdx]=['subject'=>'LUNCH BREAK','teacher'=>'','start'=>$slot['start'],'end'=>$slot['end'],'is_lunch'=>true,'is_free'=>false];continue;}
                if($af&&in_array($sIdx,$freeIdx)){$tt[$ci][$day][$sIdx]=['subject'=>'FREE PERIOD','teacher'=>'','start'=>$slot['start'],'end'=>$slot['end'],'is_lunch'=>false,'is_free'=>true];continue;}
                $cands=[];
                for($si=0;$si<$ns;$si++){
                    if(in_array($si,$used))continue; if($quota[$ci][$si]<=0)continue;
                    $ti=$stm[$si]??null; if($ti===null)continue;
                    if(teacherOnLunch($teachers[$ti],$slot))continue;
                    if(!empty($booked[$day][$sIdx][$ti]))continue;
                    $cands[]=['si'=>$si,'ti'=>$ti,'quota'=>$quota[$ci][$si]];
                }
                if(!empty($cands)){
                    usort($cands,fn($a,$b)=>$b['quota']-$a['quota']);$p=$cands[0];
                    $quota[$ci][$p['si']]--;$booked[$day][$sIdx][$p['ti']]=true;$used[]=$p['si'];
                    $tt[$ci][$day][$sIdx]=['subject'=>$subjects[$p['si']]['name'],'teacher'=>$teachers[$p['ti']]['name'],'start'=>$slot['start'],'end'=>$slot['end'],'is_lunch'=>false,'is_free'=>false];
                }else{
                    $tt[$ci][$day][$sIdx]=['subject'=>'FREE PERIOD','teacher'=>'','start'=>$slot['start'],'end'=>$slot['end'],'is_lunch'=>false,'is_free'=>true];
                }
            }
        }
    }
    return $tt;
}

$timetable   = generateTimetable($classNames,$subjects,$teachers,$subjectTeacherMap,$days,$slots,$allowFree,$freePerDay,$freePosition);
$totalPeriods= $slotsPerDay;
$reportDate  = date('d/m/Y');
$facultyList = [];
foreach($subjects as $si=>$sub){$ti=$subjectTeacherMap[$si]??null;$facultyList[]=['subject'=>$sub['name'],'teacher'=>$ti!==null?$teachers[$ti]['name']:'—'];}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Timetable — <?= htmlspecialchars($schoolName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--purple:#4f46e5;--blue:#1d4ed8;--teal:#0f766e;--bg:#f1f5f9;--white:#fff;--border:#cbd5e1;--text:#0f172a;--muted:#64748b;--hdr:#1e3a5f;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);}
#cd-overlay{position:fixed;inset:0;background:linear-gradient(135deg,#1e3a5f 0%,#4f46e5 55%,#0f766e 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999;transition:opacity .6s;}
#cd-overlay.out{opacity:0;pointer-events:none;}
.cd-icon{font-size:3rem;margin-bottom:18px;animation:bob 1s infinite;}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.cd-lbl{font-size:.9rem;font-weight:600;color:rgba(255,255,255,.7);letter-spacing:2px;text-transform:uppercase;margin-bottom:28px;}
.cd-ring{width:120px;height:120px;position:relative;margin-bottom:24px;}
.cd-ring svg{transform:rotate(-90deg);}
.cd-ring circle{fill:none;stroke-width:8;}
.cd-track{stroke:rgba(255,255,255,.15);}
.cd-fill{stroke:#fff;stroke-linecap:round;stroke-dasharray:314;stroke-dashoffset:314;transition:stroke-dashoffset .9s linear;}
.cd-num{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:2.8rem;font-weight:900;color:#fff;}
.cd-steps{display:flex;gap:10px;}
.cd-step{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:8px 16px;font-size:.72rem;color:rgba(255,255,255,.55);font-weight:600;transition:all .4s;}
.cd-step.on{background:rgba(255,255,255,.25);border-color:rgba(255,255,255,.5);color:#fff;}
#app{display:none;}
nav{background:var(--white);border-bottom:2px solid var(--border);padding:14px 48px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,.08);}
.nav-logo{font-size:1.4rem;font-weight:900;background:linear-gradient(135deg,var(--purple),var(--teal));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-decoration:none;}
.nav-actions{display:flex;gap:10px;}
.btn-back,.btn-print{padding:9px 18px;border-radius:8px;font-size:.82rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;text-decoration:none;transition:all .2s;}
.btn-back{background:var(--white);border:1.5px solid var(--border);color:var(--muted);}
.btn-back:hover{border-color:var(--purple);color:var(--purple);}
.btn-print{background:linear-gradient(135deg,var(--purple),var(--blue));border:none;color:#fff;box-shadow:0 4px 14px rgba(79,70,229,.3);}
.btn-print:hover{opacity:.9;}
.wrap{max-width:1200px;margin:0 auto;padding:36px 24px 80px;}
.summary{background:linear-gradient(135deg,var(--hdr) 0%,var(--purple) 55%,var(--teal) 100%);border-radius:16px;padding:22px 28px;margin-bottom:28px;display:flex;gap:28px;flex-wrap:wrap;align-items:center;box-shadow:0 8px 32px rgba(79,70,229,.2);}
.sum-title{flex:1;min-width:200px;}
.sum-title h1{font-size:1.35rem;font-weight:800;color:#fff;}
.sum-title p{font-size:.76rem;color:rgba(255,255,255,.7);margin-top:4px;}
.sum-stats{display:flex;gap:14px;flex-wrap:wrap;}
.stat{text-align:center;background:rgba(255,255,255,.15);border-radius:10px;padding:10px 16px;}
.stat .n{font-size:1.5rem;font-weight:800;color:#fff;}
.stat .l{font-size:.62rem;color:rgba(255,255,255,.7);margin-top:2px;text-transform:uppercase;letter-spacing:.5px;}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;}
.tab{padding:8px 20px;border-radius:8px;font-size:.82rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;border:1.5px solid var(--border);background:var(--white);color:var(--muted);transition:all .2s;}
.tab.on,.tab:hover{background:var(--purple);border-color:var(--purple);color:#fff;}
.tt-wrap{display:none;}.tt-wrap.on{display:block;}
.sheet{background:var(--white);border:2.5px solid var(--hdr);border-radius:4px;overflow:hidden;box-shadow:0 4px 28px rgba(0,0,0,.1);}
.pdf-hdr{background:var(--hdr);color:#fff;text-align:center;padding:16px 24px 12px;border-bottom:3px solid var(--purple);}
.pdf-hdr h2{font-size:1.2rem;font-weight:800;letter-spacing:.5px;margin-bottom:8px;}
.pdf-meta{display:flex;justify-content:center;gap:24px;flex-wrap:wrap;font-size:.76rem;color:rgba(255,255,255,.8);font-weight:500;}
.pdf-meta strong{color:#fff;}
.pdf-scroll{overflow-x:auto;}
.pdf-tbl{width:100%;border-collapse:collapse;min-width:700px;font-size:.77rem;}
.pdf-tbl thead tr:first-child th{background:var(--hdr);color:#fff;border:1.5px solid #2d4f7c;padding:10px 8px;text-align:center;font-weight:700;font-size:.74rem;}
.pdf-tbl thead tr:first-child th:first-child{background:#162d4a;min-width:68px;font-size:.68rem;}
.pdf-tbl thead tr.trow th{background:#2d4f7c;color:#d0e4ff;border:1.5px solid #3a5f8a;padding:7px 8px;text-align:center;font-size:.68rem;font-weight:600;}
.pdf-tbl thead tr.trow th:first-child{background:var(--hdr);color:rgba(255,255,255,.45);font-size:.62rem;}
.pdf-tbl tbody tr{border-bottom:1px solid #dde4ef;}
.pdf-tbl tbody tr:nth-child(even){background:#f7f9fc;}
.pdf-tbl tbody tr:hover{background:#eef2ff;}
.day-cell{background:var(--hdr)!important;color:#fff;font-weight:800;font-size:.8rem;text-align:center;padding:0 10px;border-right:2px solid #2d4f7c;vertical-align:middle;letter-spacing:.5px;}
.sub-cell{border:1px solid #dde4ef;padding:10px 8px;text-align:center;vertical-align:middle;min-width:108px;}
.sub-name{font-weight:700;color:var(--hdr);font-size:.76rem;line-height:1.4;margin-bottom:3px;}
.sub-tchr{font-size:.66rem;color:var(--purple);font-style:italic;}
.lunch-cell{background:#fef9c3!important;border:1.5px solid #fbbf24;text-align:center;vertical-align:middle;padding:8px 6px;min-width:80px;}
.lunch-txt{font-weight:800;font-size:.75rem;color:#92400e;letter-spacing:.5px;}
.lunch-cell-hdr{background:#fef3c7!important;color:#92400e;border:1.5px solid #fbbf24;text-align:center;vertical-align:middle;padding:8px 6px;font-weight:700;font-size:.72rem;min-width:80px;}
.free-cell{background:#f1f5f9!important;border:1px solid #dde4ef;text-align:center;vertical-align:middle;padding:10px;color:#94a3b8;font-size:.7rem;font-style:italic;}
.fac-sec{border-top:2px solid var(--hdr);}
.fac-tbl{width:100%;border-collapse:collapse;font-size:.74rem;}
.fac-tbl thead th{background:var(--hdr);color:#fff;padding:8px 14px;text-align:left;font-weight:700;font-size:.7rem;letter-spacing:.5px;border:1px solid #2d4f7c;}
.fac-tbl tbody td{padding:7px 14px;border:1px solid #dde4ef;color:var(--text);}
.fac-tbl tbody tr:nth-child(even) td{background:#f7f9fc;}
.pdf-foot{display:flex;justify-content:space-between;align-items:flex-end;padding:16px 24px;border-top:1px solid #dde4ef;background:#f8fafc;}
.sig{text-align:center;}.sig-line{border-top:1.5px solid #94a3b8;width:160px;margin:8px auto 5px;}
.sig-name{font-weight:700;font-size:.8rem;color:var(--text);}.sig-role{font-size:.68rem;color:var(--muted);margin-top:2px;}
.gen-info{font-size:.66rem;color:var(--muted);text-align:center;}
.legend{display:flex;gap:14px;flex-wrap:wrap;margin-top:12px;padding:11px 15px;background:var(--white);border:1px solid var(--border);border-radius:8px;}
.leg-item{display:flex;align-items:center;gap:7px;font-size:.72rem;color:var(--muted);}
.leg-dot{width:10px;height:10px;border-radius:3px;}
footer{background:var(--white);border-top:1px solid var(--border);color:var(--muted);text-align:center;padding:18px;font-size:.74rem;margin-top:44px;}
footer span{color:var(--purple);font-weight:600;}
@media print{#cd-overlay{display:none!important;}nav,.tabs,.legend,footer,.btn-print,.btn-back,.summary{display:none!important;}#app{display:block!important;}.tt-wrap{display:block!important;page-break-after:always;}.sheet{box-shadow:none;border:2px solid #000;}body{background:#fff;}}
@media(max-width:640px){nav{padding:12px 18px;}.wrap{padding:20px 12px 60px;}}
</style>
</head>
<body>
<div id="cd-overlay">
  <div class="cd-icon">📅</div>
  <div class="cd-lbl">Building Your Timetable</div>
  <div class="cd-ring">
    <svg width="120" height="120" viewBox="0 0 120 120">
      <circle class="cd-track" cx="60" cy="60" r="50"/>
      <circle class="cd-fill" id="cd-c" cx="60" cy="60" r="50"/>
    </svg>
    <div class="cd-num" id="cd-n">3</div>
  </div>
  <div class="cd-steps">
    <div class="cd-step" id="s1">⚙ Processing Input</div>
    <div class="cd-step" id="s2">🔀 Scheduling Periods</div>
    <div class="cd-step" id="s3">✅ Ready</div>
  </div>
</div>
<div id="app">
<nav>
  <a href="/" class="nav-logo">📅 TimeTable</a>
  <div class="nav-actions">
    <a href="/" class="btn-back">← New Timetable</a>
    <button class="btn-print" onclick="window.print()">🖨 Print / PDF</button>
  </div>
</nav>
<div class="wrap">
  <div class="summary">
    <div class="sum-title">
      <h1><?= htmlspecialchars($schoolName) ?></h1>
      <p><?= $daysPerWeek ?> days/week · <?= $periodDuration ?>-min periods · <?= $slotsPerDay ?> periods/day · ~<?= $basePerWeek ?>–<?= $basePerWeek+($extraSubjects>0?1:0) ?> periods/subject/week<?= $allowFree?' · '.$freePerDay.' free/day ('.$freePosition.')':' · No free periods' ?></p>
    </div>
    <div class="sum-stats">
      <div class="stat"><div class="n"><?= count($classNames) ?></div><div class="l">Classes</div></div>
      <div class="stat"><div class="n"><?= count($subjects) ?></div><div class="l">Subjects</div></div>
      <div class="stat"><div class="n"><?= count($teachers) ?></div><div class="l">Teachers</div></div>
      <div class="stat"><div class="n"><?= $totalPeriods ?></div><div class="l">Periods/Day</div></div>
    </div>
  </div>
  <div class="tabs">
    <?php foreach($classNames as $ci=>$cn): ?>
      <button class="tab <?= $ci===0?'on':'' ?>" onclick="show(<?= $ci ?>)"><?= htmlspecialchars($cn) ?></button>
    <?php endforeach; ?>
  </div>
  <?php foreach($classNames as $ci=>$cn): ?>
  <div class="tt-wrap <?= $ci===0?'on':'' ?>" id="c<?= $ci ?>">
    <div class="sheet">
      <div class="pdf-hdr">
        <h2><?= htmlspecialchars($schoolName) ?></h2>
        <div class="pdf-meta">
          <span>Class: <strong><?= htmlspecialchars($cn) ?></strong></span>
          <span>Days: <strong><?= $daysPerWeek ?>/week</strong></span>
          <span>Period: <strong><?= $periodDuration ?> min</strong></span>
          <span>W.E.F.: <strong><?= $reportDate ?></strong></span>
          <span>Periods/Day: <strong><?= $slotsPerDay ?></strong></span>
        </div>
      </div>
      <div class="pdf-scroll">
      <?php
        $lunchIdx=null;
        foreach($slots as $si=>$sl){if($sl['is_lunch']){$lunchIdx=$si;break;}}
        $nlSlots=array_filter($slots,fn($s)=>!$s['is_lunch']);
      ?>
      <table class="pdf-tbl">
        <thead>
          <tr>
            <th rowspan="2">Time<br>Days</th>
            <?php $li=false; foreach($nlSlots as $si=>$sl): ?>
              <?php if(!$li&&$lunchIdx!==null&&$si>$lunchIdx): $li=true; ?>
                <th rowspan="2" class="lunch-cell-hdr"><?= $lunchStart ?> – <?= $lunchEnd ?></th>
              <?php endif; ?>
              <th><?= $sl['start'] ?> – <?= $sl['end'] ?></th>
            <?php endforeach; ?>
          </tr>
          <tr class="trow">
            <?php $pn=1; $li2=false; foreach($nlSlots as $si=>$sl):
              if(!$li2&&$lunchIdx!==null&&$si>$lunchIdx): $li2=true; ?>
                <th class="lunch-cell-hdr" style="font-size:.65rem;">🍽 Lunch</th>
              <?php endif; ?>
              <th>Period <?= $pn++ ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach($days as $day): ?>
          <tr>
            <td class="day-cell"><?= substr($day,0,3) ?></td>
            <?php foreach($slots as $si=>$sl):
              if($sl['is_lunch']): ?>
                <td class="lunch-cell"><div class="lunch-txt">Break</div></td>
              <?php continue; endif;
              $cell=$timetable[$ci][$day][$si]??null;
            ?>
              <?php if(!$cell||$cell['is_free']): ?>
                <td class="free-cell">—</td>
              <?php else: ?>
                <td class="sub-cell">
                  <div class="sub-name"><?= htmlspecialchars($cell['subject']) ?></div>
                  <?php if($cell['teacher']): ?>
                    <div class="sub-tchr">(<?= htmlspecialchars($cell['teacher']) ?>)</div>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <div class="fac-sec">
        <table class="fac-tbl">
          <thead><tr><th style="width:50%">Subject</th><th style="width:50%">Faculty</th></tr></thead>
          <tbody>
            <?php foreach($facultyList as $f): ?>
            <tr><td><?= htmlspecialchars($f['subject']) ?></td><td><?= htmlspecialchars($f['teacher']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="pdf-foot">
        <div class="sig"><div class="sig-line"></div><div class="sig-name">Class Teacher</div><div class="sig-role"><?= htmlspecialchars($cn) ?></div></div>
        <div class="gen-info">Generated on <?= $reportDate ?><br><?= htmlspecialchars($schoolName) ?></div>
        <div class="sig"><div class="sig-line"></div><div class="sig-name">Time Table Incharge</div><div class="sig-role">Academic Department</div></div>
      </div>
    </div>
    <div class="legend">
      <div class="leg-item"><div class="leg-dot" style="background:#dbeafe;border:1.5px solid #1d4ed8;"></div> Subject Period</div>
      <div class="leg-item"><div class="leg-dot" style="background:#fef9c3;border:1.5px solid #fbbf24;"></div> Lunch Break</div>
      <div class="leg-item"><div class="leg-dot" style="background:#f1f5f9;border:1.5px solid #cbd5e1;"></div> Free Period</div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<footer><p>© <?= date('Y') ?> <span>TimeTable Generator</span> — Smart School Schedule Builder</p></footer>
</div>
<script>
(function(){
  const ov=document.getElementById('cd-overlay'),num=document.getElementById('cd-n'),circ=document.getElementById('cd-c'),
        steps=[document.getElementById('s1'),document.getElementById('s2'),document.getElementById('s3')];
  let c=3;const CIRC=314;
  function tick(){
    num.textContent=c;circ.style.strokeDashoffset=CIRC*(c/3);
    if(c===3)steps[0].classList.add('on');if(c===2)steps[1].classList.add('on');if(c===1)steps[2].classList.add('on');
    if(c===0){ov.classList.add('out');setTimeout(()=>{ov.style.display='none';document.getElementById('app').style.display='block';},650);return;}
    c--;setTimeout(tick,1000);
  }
  setTimeout(tick,400);
})();
function show(i){
  document.querySelectorAll('.tt-wrap').forEach(e=>e.classList.remove('on'));
  document.querySelectorAll('.tab').forEach(e=>e.classList.remove('on'));
  document.getElementById('c'+i).classList.add('on');
  document.querySelectorAll('.tab')[i].classList.add('on');
}
</script>
</body>
</html>
