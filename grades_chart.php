<?php
require_once('../../config.php');
//instantiate chart form
require_once('chart_form.php');

global $DB, $OUTPUT, $PAGE, $USER;

// Check for all required variables.
$courseid = required_param('courseid', PARAM_INT);
$userid = $USER->id;
$blockid = required_param('blockid', PARAM_INT);

// Next look for optional variables
$id = optional_param('id', 0, PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourse', 'block_la_dashboard', $courseid);
}

require_login($course);

$PAGE->set_url('/blocks/la_dashboard/grades_chart.php', array('id' => $courseid));
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('chart_view', 'block_la_dashboard'));


$charto = new chart_form();

if ($charto->is_cancelled()){
	// Cancelled forms redirect to the course main page.
	$courseurl = new moodle_url('/course/grades_chart.php', array('id' => $id));
	redirect($courseurl);
} else if ($charto->get_data()){
	// We need to add code to appropriately act on and store the submitted data
    // but for now we will just redirect back to the course main page.
	$courseurl = new moodle_url('/course/grades_chart.php', array('id' => $id));
	redirect($courseurl);
} else {
	// form didn't validate or this is the first display
	$site = get_site();
	echo $OUTPUT->header();
	$charto->display();
}

// QUERIES
// roles OK
$sql_student = <<<EOD
SELECT count(*) as co FROM mdl_role r 
JOIN mdl_role_assignments as ra on ra.roleid = r.id 
JOIN mdl_user as u on u.id = ra.userid
JOIN mdl_user_enrolments as ue on ue.userid = u.id
JOIN mdl_enrol as e on e.id = ue.enrolid
WHERE r.shortname = 'student' AND ra.userid = $userid AND e.courseid = $courseid
EOD;
$sql_teacher = <<<EOD
SELECT count(*) as co FROM mdl_role r 
JOIN mdl_role_assignments as ra on ra.roleid = r.id 
JOIN mdl_user as u on u.id = ra.userid
JOIN mdl_user_enrolments as ue on ue.userid = u.id
JOIN mdl_enrol as e on e.id = ue.enrolid
WHERE r.shortname = 'teacher' AND ra.userid = $userid AND e.courseid = $courseid
EOD;

// num of finished lessons by user OK
$sql_lessons_e = <<<EOD
SELECT COUNT(DISTINCT l.id) FROM mdl_lesson_timer t
RIGHT JOIN mdl_lesson l on l.id = t.lessonid
WHERE t.userid=$userid and l.course=$courseid and t.completed=1
EOD;

// sum of lessons OK
$sql_lessons = <<<EOD
SELECT COUNT(L.id) FROM mdl_lesson L
WHERE L.course=$courseid
EOD;

// num of finished quizes by user OK
$sql_quiz_e =<<<EOD
SELECT COUNT(q.id) FROM mdl_quiz_grades g
JOIN mdl_quiz q on q.id = g.quiz
WHERE q.course=$courseid and g.userid=$userid
EOD;

// sum of tests on course OK
$sql_quiz_sum = <<<EOD
SELECT COUNT(DISTINCT q.id) FROM mdl_quiz_grades g 
JOIN mdl_quiz q on q.id = g.quiz
where q.course=$courseid
EOD;

//average quiz grade OK
$sql_quiz_avg = <<<EOD
SELECT (sum(g.grade)/count(g.quiz))/10 as aver FROM mdl_quiz_grades g
RIGHT JOIN mdl_quiz q on q.id = g.quiz 
WHERE q.course=$courseid and g.userid=$userid 
GROUP BY g.userid 
EOD;

//average lesson grade OK
$sql_lessons_avg = <<<EOD
SELECT (sum(g.grade)/count(l.id))/100 FROM mdl_lesson_grades g
RIGHT JOIN mdl_lesson as l on l.id = g.lessonid
WHERE l.course=$courseid and g.userid=$userid
GROUP BY g.userid
EOD;

//average assignment grade OK
$sql_assign_avg = <<<EOD
SELECT sum(g.grade)/count(a.id)/100 from mdl_assign_grades g
RIGHT JOIN mdl_assign a on a.id = g.assignment
WHERE g.userid = $userid and a.course=$courseid
GROUP BY g.userid
EOD;

//Timeline
//rokovi predaje assignment OK
$sql_assign_time =<<<EOD
SELECT DISTINCT a.name, a.duedate FROM mdl_assign a 
LEFT JOIN mdl_assign_user_mapping um on um.assignment = a.id 
WHERE a.course=$courseid
EOD;

$sql_quiz_time=<<<EOD
SELECT q.timeclose, q.name FROM mdl_quiz q
WHERE q.course=$courseid
EOD;

// avg
$assign_avg = get_num_from_query($sql_assign_avg);
$lessons_avg = get_num_from_query($sql_lessons_avg);
$quiz_avg = get_num_from_query($sql_quiz_avg);

// roles
$student = get_num_from_query($sql_student);
$teacher = get_num_from_query($sql_teacher);

$role = 0;
//prediction i napredak u ulozi studenta
if ($student){
	$role = 1;
	$url = new moodle_url('/blocks/la_dashboard/id3.php', array('courseid' => $COURSE->id));
	$predict = html_writer::link($url, get_string('predvidi_student', 'block_la_dashboard'));
	get_all_grades($courseid, $role);

	// progress charts DONE
	$lessons_sum = get_num_from_query($sql_lessons);
	$lessons_e = get_num_from_query($sql_lessons_e);

	$quiz_sum = get_num_from_query($sql_quiz_sum);
	$quiz_e = get_num_from_query($sql_quiz_e);


	$data_progress = array(
		array('Tip', 'Ukupno', 'Riješeno'),
		array('Lekcije', (int)$lessons_sum, (int)$lessons_e),
		array('Testovi', (int)$quiz_sum, (int)$quiz_e),
	);

	$jsonTableLesson = json_encode($data_progress);
} else{
	$url = new moodle_url('/blocks/la_dashboard/id3.php', array('courseid' => $COURSE->id, 'role' => 0));
	//get_all_grades($courseid, 0); //nema dovoljno podataka, pa koristimo isti set 
	$predict = html_writer::link($url, get_string('predvidi_teacher', 'block_la_dashboard'));
	
	// weak spots chart
	$data_spotss = array(array('Naziv', 'Kritično'));
	$p = get_weak_spots($courseid);

	foreach($p as $v){
		array_push($data_spotss, $v);
	}

	$jsonTableWeak = json_encode($data_spotss);
	$slabe_tocke = '<b><i>Slabe točke kolegija</i></b></br>Na idućem grafu prikazane su slabe točke kolegija. Naziv lekcije, zadaće ili testa koji ima prosječnu ocjenu manju od 50% ima vrijednost 1 na grafu "Slabe točke kolegija".';
}

$r = "Naziv provjere ---> Istek roka </br>";
$r .= get_timeline_data($sql_quiz_time);
$r .= get_timeline_data($sql_assign_time);

function get_weak_spots($courseid){
	global $DB;
	$sql_lesson_weak=<<<EOD
SELECT l.name, CONCAT(AVG(g.grade),':', l.grade) FROM mdl_lesson l
LEFT JOIN mdl_lesson_grades g on g.lessonid = l.id
WHERE l.course=$courseid
GROUP BY l.id
EOD;
	$sql_quiz_weak = <<<EOD
SELECT q.name, CONCAT(AVG(g.grade), ':', q.grade) FROM mdl_quiz q
LEFT JOIN mdl_quiz_grades g on g.quiz = q.id
WHERE q.course=$courseid
GROUP BY q.id
EOD;
	$sql_assign_weak = <<<EOD
SELECT a.name, CONCAT(AVG(g.grade), ':', a.grade) FROM mdl_assign a
LEFT JOIN mdl_assign_grades g on g.assignment = a.id
WHERE a.course=$courseid
GROUP BY a.id
EOD;
	
	$result = $DB->get_records_sql_menu($sql_lesson_weak);

	$data_less = array();
	foreach($result as $k => $v){
		if (!$v){
			continue;
		}
		$concat = explode(":", $v);
		$subless = array();
		array_push($subless, $k);
		foreach($concat as $vl){
			array_push($subless, (float)$vl);
		}
		array_push($data_less, $subless);
	}
	
	$result = $DB->get_records_sql_menu($sql_quiz_weak);
	$data_quiz = array();
	foreach($result as $k => $v){
		if (!$v){
			continue;
		}
		$concat = explode(":", $v);
		$subquiz = array();
		array_push($subquiz, $k);
		foreach($concat as $vl){
			array_push($subquiz, (float)$vl);
		}
		array_push($data_quiz, $subquiz);
	}
	
	$result = $DB->get_records_sql_menu($sql_assign_weak);
	$data_assign = array();
	foreach($result as $k => $v){
		if (!$v){
			continue;
		}
		$concat = explode(":", $v);
		
		$subassign = array();
		array_push($subassign, $k);
		foreach($concat as $vl){
			array_push($subassign, (float)$vl);
		}
		array_push($data_assign, $subassign);
	}
	

	$merged = array_merge($data_less, $data_quiz, $data_assign);
	
	$final_data = half_round($merged);
	return $final_data;

}

function half_round($data){
	/*
	spoji sve tri vrste, za svaki name odredi je l kriticno il nije
	Primjer retka:
	final_data = ['lesson_name1'[0.4, 1], 'lesson_name2'[3, 10]] za taj course
	*/

	/// DOVRSI TO NORMALNIJE GLAVE !!

	$table_data = array();
	foreach($data as $v){
		if(($v[1]/$v[2]) < ($v[2]/200)){
			$state = 1;
			array_push($table_data, array($v[0], $state));
		} else{
			$state = 0;
			array_push($table_data, array($v[0], $state));
		}

	}

	return $table_data;
}
function get_timeline_data($q){
	global $DB;

	$data = "";
	$result = $DB->get_records_sql_menu($q);
	foreach($result as $k => $v){
		if ($k == 0){
			continue;
		}
		$data .= $v." ---> ";
		$date = new DateTime('@'.$k);
		$data .= $date->format('Y-m-d H:i').' </br>';
	}
	return $data;

}

function get_num_from_query($q){
	global $DB;
	
	$result = $DB->get_records_sql_menu($q);
	foreach($result as $k => $v){
		return $k;
	}
}

function round_data($data){
	//data is array of avg grades
	$res = array();
	foreach ($data as $v){
		if ($v < 0.5){
			$v_r = 0.2;
			
		} else if ($v > 0.8){
			$v_r = 0.9;
		}else{
			$v_r = 0.6;
		}
		array_push($res, $v_r);
	}
	return $res;
}

function get_all_grades($courseid, $role){
	global $DB;

	$sql_lessons =<<<EOD
SELECT IFNULL(sum(g.grade)/count(g.id)/100, 0)
FROM mdl_user u
LEFT JOIN mdl_user_enrolments enr on enr.userid = u.id
LEFT JOIN mdl_lesson_grades g on g.userid = u.id
LEFT JOIN mdl_enrol en on en.id=enr.enrolid
LEFT JOIN mdl_lesson l on l.id = g.lessonid
LEFT JOIN mdl_course c on c.id = l.course
WHERE c.id=$courseid
GROUP BY u.id
EOD;
	$sql_quizes =<<<EOD
SELECT IFNULL(sum(g.grade)/count(g.id)/10, 0) FROM mdl_user u
LEFT JOIN mdl_user_enrolments enr on enr.userid = u.id
LEFT JOIN mdl_quiz_attempts qat on qat.userid = u.id
LEFT JOIN mdl_enrol en on en.id = enr.enrolid
LEFT JOIN mdl_course c on c.id = en.courseid
LEFT JOIN mdl_quiz_grades g on g.quiz = qat.quiz
WHERE en.courseid=$courseid
GROUP BY u.id
EOD;
	$sql_assign =<<<EOD
SELECT IFNULL(sum(g.grade)/count(g.id)/10, 0) 
FROM mdl_user u 
LEFT JOIN mdl_user_enrolments enr on enr.userid = u.id 
LEFT JOIN mdl_enrol en on en.id = enr.enrolid 
LEFT JOIN mdl_course c on c.id = en.courseid 
LEFT JOIN mdl_assign asa on asa.course = c.id 
LEFT JOIN mdl_assign_grades g on g.assignment = asa.id 
WHERE c.id=$courseid
GROUP BY u.id 
EOD;
	$final_data = array();
	array_push($final_data, 'Zadace', 'Testovi', 'Lekcije');


	$result_db = $DB->get_records_sql_menu($sql_lessons);
	$result=array();
	foreach($result_db as $k => $v){
		array_push($result, $k);
	};
	$res_lesson = round_data($result);
	
	$result_db = $DB->get_records_sql_menu($sql_quizes);
	$result=array();
	foreach($result_db as $k => $v){
		array_push($result, $k);
	};
	$res_quiz = round_data($result);
	
	$result_db = $DB->get_records_sql_menu($sql_assign);
	$result=array();
	foreach($result_db as $k => $v){
		array_push($result, $k);
	};
	$res_assign = round_data($result);
	
	$data = trim_data($res_assign, $res_quiz, $res_lesson);
	
	foreach($data as $row){
		array_push($final_data, $row);
	}
	$prediction_data = str_putcsv($final_data, $role); //temp.csv
}

function trim_data($a, $b, $c){	
	$min_len = min(count($a), count($b), count($c));
	
	$a = array_slice($a, 0, $min_len);
	$b = array_slice($b, 0, $min_len);
	$c = array_slice($c, 0, $min_len);
	
	// transpose data
	$re = array();
	for ($i = 0; $i <= min_len; $i++){
		$row = array($a[$i], $b[$i], $c[$i]);
		array_push($re, $row);
	};
	
	return $re;
}

function str_putcsv($data, $role) {
        // Generate CSV data from array
		if ($role==1){
			$fh = fopen('temp.csv', 'w'); // create file
		} else{
			$fh = fopen('input_data1.csv', 'w'); // create file
		}

        // write out the headers
        fputcsv($fh, array("Zadace", "Testovi", "Lekcije"));
        // write out the data
        foreach ( $data as $row ) {
                fputcsv($fh, $row);
        }
        //rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
}
?>
<!DOCTYPE html>
<html>
  <head>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
	<script type="text/javascript">
      google.charts.load("current", {packages:['corechart']});
      google.charts.setOnLoadCallback(drawChart);
	  
      function drawChart() {
		// bar chart progress student + uvjet
		var data1 = google.visualization.arrayToDataTable(<?php echo $jsonTableLesson ?>);
        var options1 = {
          title: 'Postotak riješenosti',
		  legend: { position: 'top'},
		  isStacked: true
        };
        var chart1 = new google.visualization.BarChart(document.getElementById('lessons'));
        chart1.draw(data1, options1);
		
		// weak spots
		var data2 = google.visualization.arrayToDataTable(<?php echo $jsonTableWeak ?>);
        var options2 = {
          title: 'Slabe točke kolegija',
		  legend: { position: 'top'}
        };
        var chart2 = new google.visualization.ColumnChart(document.getElementById('weak_spots'));
        chart2.draw(data2, options2);
		
	  }
	</script>
  </head>
  <body>
	<table>
	<tr>
		<td><div id ="predict" style="width: 500px; height: 30px;"></div><b><i>Link za predviđanje ocjene/ocjena</i></b></br><?php echo $predict ?></br></br></br></td>
	</tr>
	<tr>
		<td><div id ="timeline" style="width: 500px; height: 30px;"></div><b><i>Informacije o nadolazećim testovima i zadaćama koji imaju rok predaje</i></b></br><?php echo $r ?> </br></td>

	</tr>
	<tr>
		<td><div id="lessons" style="width: 500px; height: 500px;"></td>
	</tr>
	<tr>
			<td><div id="weak_spoto_desc" style="width: 1000px; height: 150px;"><?php echo $slabe_tocke ?></div></td>
	</tr>
	<tr>
		<td><div id="weak_spots" style="width: 1000px; height: 150px;"></div></td>
	</tr>
	</table>
  </body>
</html>

<?php
echo $OUTPUT->footer();
?>
	
