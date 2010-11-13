<?php
/**
 * This allows you to edit questions for a realtimequiz
 *
 * @author: Davosmith
 * @package realtimequiz
 **/


	require_once('../../config.php');
	require_once('lib.php');

    $id = optional_param('id', false, PARAM_INT);
    $quizid = optional_param('quizid', false, PARAM_INT);
	$action = optional_param('action', 'listquestions', PARAM_ALPHA);
	$questionid = optional_param('questionid', 0, PARAM_INT);

    $addanswers = optional_param('addanswers', false, PARAM_BOOL);
    $saveadd = optional_param('saveadd', false, PARAM_BOOL);
    $canceledit = optional_param('cancel', false, PARAM_BOOL);

    if ($id) {
        if (! $cm = get_record("course_modules", "id", $id)) {
            error("Course Module ID was incorrect");
        }
    
        if (! $course = get_record("course", "id", $cm->course)) {
            error("Course is misconfigured");
        }
    
        if (! $quiz = get_record("realtimequiz", "id", $cm->instance)) {
            error("Course module is incorrect");
        }

        $quizid = $quiz->id;
        
    } else {
        if (! $quiz = get_record('realtimequiz', 'id', $quizid)) {
            error("Quiz id ($quizid) is incorrect");
        }
        if (! $course = get_record('course', 'id', $quiz->course)) {
            error("Course is misconfigured");
        }
        if (! $cm = get_coursemodule_from_instance('realtimequiz', $quiz->id, $course->id)) {
            error("Course Module ID was incorrect");
        }
    }
	
	require_login($course->id);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
	require_capability('mod/realtimequiz:editquestions', $context);
	add_to_log($course->id, "realtimequiz", "update: $action", "edit.php?quizid=$quizid");
		
	// Some useful functions:
	function realtimequiz_list_questions($quizid, $cm) {
		global $CFG;
	
		echo '<h2>'.get_string('questionslist','realtimequiz').'</h2>';	
		
		$questions = get_records('realtimequiz_question', 'quizid', $quizid, 'questionnum');
		$questioncount = count($questions);
		$expectednumber = 1;
		echo '<ol>';
	    if (!empty($questions)) {
		    foreach ($questions as $question) {
			    // A good place to double-check the question numbers and fix any that are broken
			    if ($question->questionnum != $expectednumber) {
					$question->questionnum = $expectednumber;
					update_record('realtimequiz_question', $question);
				}
			
				$qtext = $question->questiontext;
				/*if (strlen($qtext) > 90) {
					$qtext = sprintf("%.90s...", $qtext);
                    }*/
				echo "<li><span class='realtimequiz_editquestion'>$qtext </span><span class='realtimequiz_editicons'>";
				echo "<a href='edit.php?quizid=$quizid&amp;action=editquestion&amp;questionid=$question->id'><img src='$CFG->pixpath/t/edit.gif' alt='Edit Question $question->questionnum' /></a> ";	//FIXME - translate alt text
				if ($question->questionnum > 1) {
					echo "<a href='edit.php?quizid=$quizid&amp;action=moveup&amp;questionid=$question->id'><img src='$CFG->pixpath/t/up.gif' alt='Move Question $question->questionnum Up' /></a> ";	//FIXME - translate alt text
				} else {
                    echo "<img src='$CFG->pixpath/spacer.gif' width='15px' />";
                }
				if ($question->questionnum < $questioncount) {
					echo "<a href='edit.php?quizid=$quizid&amp;action=movedown&amp;questionid=$question->id'><img src='$CFG->pixpath/t/down.gif' alt='Move Question $question->questionnum Down' /></a> ";	//FIXME - translate alt text
				} else {
                    echo "<img src='$CFG->pixpath/spacer.gif' width='15px' />";
                }
                echo '&nbsp;';
				echo "<a href='edit.php?quizid=$quizid&amp;action=deletequestion&amp;questionid=$question->id'><img src='$CFG->pixpath/t/delete.gif' alt='Delete Question $question->questionnum' /></a>";	//FIXME - translate alt text
				echo '</span></li>';
				$expectednumber++;
			}
		}
		echo '</ol>';
		echo "<form method='post' action='$CFG->wwwroot/mod/realtimequiz/edit.php?quizid=$quizid&amp;action=addquestion'>";
		echo '<input type=\'submit\' value=\''.get_string('addquestion','realtimequiz').'\'></input></form>';
	}
	
	function realtimequiz_edit_question($quizid, $questionid='', $minanswers=4) {
		global $CFG;
			
		echo '<center>';
		if ($questionid=='') {
			$action = 'doaddquestion';
			$question = new stdClass();
			$question->id = 0;
			$question->quizid = $quizid;
			$question->questionnum = count_records('realtimequiz_question', 'quizid', $quizid) + 1;
			$question->questiontext = '';
			$question->questiontime = 0;
			$question->image = '';
			echo '<h2>'.get_string('addingquestion','realtimequiz').$question->questionnum.'</h2>';
			
			$answers = array();
		} else {
			$action = 'doeditquestion';
			$question = get_record('realtimequiz_question', 'id', $questionid);
			if (!$question) {
				error("Question not found");
			}
			echo '<h2>'.get_string('edittingquestion','realtimequiz').$question->questionnum.'</h2>';
			
			$answers = get_records('realtimequiz_answer', 'questionid', $questionid, 'id');
		}
		
        if (optional_param('saveadd', false, PARAM_BOOL)) {
            // When clicking 'save and add another' we want to ignore any parameters hanging around
            $answertexts = false;
            $answercorrects = false;
            $answerids = false;

        } else {
            // Override the above values with any parameters passed in
            $question->id = optional_param('questionid',$question->id, PARAM_INT);
            $question->questiontext = optional_param('questiontext', $question->questiontext, PARAM_TEXT);
            $answertexts = optional_param('answertext', false, PARAM_TEXT);
            $answercorrects = optional_param('answercorrect', false, PARAM_INT);
            $answerids = optional_param('answerid', false, PARAM_INT);
            $question->questiontime = optional_param('questiontime', $question->questiontime, PARAM_INT);
        }
		
		if ($answertexts !== false && $answercorrects !== false && $answerids !== false) {
			$answers = array();
			$answercount = count($answertexts);
			for ($i=1; $i<=$answercount; $i++) {
				$answers[$i] = new stdClass();
				$answers[$i]->id = $answerids[$i];
				$answers[$i]->answertext = $answertexts[$i];
				$answers[$i]->correct = $answercorrects[$i];
			}
		}
		
		
		echo "<form method='post' action='$CFG->wwwroot/mod/realtimequiz/edit.php?quizid=$quizid' enctype='multipart/formdata'>";
		echo '<table cellpadding="5">
		<tr valign="top">
		<td align="right"><b>'.get_string('questiontext','realtimequiz').'</b></td>
		<td>';		
		if ($question->image) {
		    $filename = $CFG->dataroot.'/'.$question->image;
		    if (file_exists($filename)) {
                $size = getimagesize($filename);
                if ($size) {
                    $imagewidth = $size[0];
                    $imageheight = $size[1];
                    if ($imagewidth > 500) {
                        $scale = 500 / $imagewidth;
                        $imagewidth = 500;
                        $imageheight *= $scale;
                    }
                    if ($imageheight > 500) {
                        $scale = 500 / $imageheight;
                        $imageheight = 500;
                        $imagewidth *= $scale;
                    }
		            $imgsrc = $CFG->wwwroot.'/file.php?file=/'.$question->image.'&t='.time();
		            echo '<image src="'.$imgsrc.'" style="float:right; border: 1px solid black;" width="'.$imagewidth.'px" height="'.$imageheight.'px" />';
	            }
	        }
		}
		echo '<textarea name="questiontext" rows="5" cols="50">'.$question->questiontext.'</textarea><br style="clear:both;" /></td>
		</tr><tr>
		<td align="right"><b>'.get_string('editquestiontime','realtimequiz').'</b></td>
		<td><input type="text" name="questiontime" size="30" value="'.$question->questiontime.'" /></td>
		</tr><tr>
		<td align="right"><b>'.get_string('questionimage','realtimequiz').'</b></td>
		<td><input type="file" name="imagefile" /></td>
		</tr><tr>
		<td align="right"><b>'.get_string('removeimage','realtimequiz').'</b></td>
		<td><input type="checkbox" name="removeimage" /></td>		
		</tr>';

		while (count($answers) < $minanswers) {
			$extraanswer = new stdClass();
			$extraanswer->id = 0;
			$extraanswer->answertext = '';
			$extraanswer->correct = 0;
			$answers[] = $extraanswer;
		}
			
		$answernum = 1;
		foreach ($answers as $answer) {
			echo '<tr><td><b>'.get_string('answer','realtimequiz').$answernum.'</b></td></tr>';
			echo '<tr valign="top">
			<td>'.get_string('answertext','realtimequiz').'</td>
			<td><input type="text" name="answertext['.$answernum.']" size="30" value="'.$answer->answertext.'" /></td>
			</tr>';
			echo '<tr valign="top">
			<td>'.get_string('correct','realtimequiz').'</td>
			<td><input name="answercorrect['.$answernum.']" type="checkbox" value="1" ';
			if ($answer->correct == 1) {
				echo 'checked="checked" ';
			}
			echo '/></td></tr>';
			echo '<input type="hidden" name="answerid['.$answernum.']" value="'.$answer->id.'" />';
			
			$answernum++;
		}
		
		echo '</table>';
		echo '<input type="hidden" name="action" value="'.$action.'" />';
		echo '<input type="hidden" name="questionid" value="'.$question->id.'" />';
		echo '<input type="hidden" name="questionnum" value="'.$question->questionnum.'" />';
		echo '<input type="hidden" name="minanswers" value="'.$minanswers.'" />';
		echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
		echo '<input type="submit" name="addanswers" value="'.get_string('addanswers', 'realtimequiz').'" />';
		echo '<p>';
        echo '<input type="submit" name="updatequestion" value="'.get_string('updatequestion', 'realtimequiz').'" />&nbsp;';
        echo '<input type="submit" name="saveadd" value="'.get_string('saveadd', 'realtimequiz').'" />&nbsp;';
        echo '<input type="submit" name="cancel" value="'.get_string('cancel').'" />';
        echo '</p>';
        echo '</form></center>';
	}
	
	function realtimequiz_confirm_deletequestion($quizid, $questionid) {
		global $CFG;
	
		echo '<center><h2>'.get_string('deletequestion', 'realtimequiz').'</h2>';
		echo '<p>'.get_string('checkdelete','realtimequiz').'</p><p>"';
		$question = get_record('realtimequiz_question', 'id', $questionid);
		echo $question->questiontext;
		echo '"</p>';
		
		echo '<form method="post" action="'.$CFG->wwwroot.'/mod/realtimequiz/edit.php?quizid='.$quizid.'">';
		echo '<input type="hidden" name="action" value="dodeletequestion" />';
		echo '<input type="hidden" name="questionid" value="'.$questionid.'" />';
		echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
		echo '<input type="submit" name="yes" value="'.get_string('yes','realtimequiz').'" /> ';
		echo '<input type="submit" name="no" value="'.get_string('no','realtimequiz').'" />';
		echo '</form></center>';

	}
	
	
	// Back to the main code
    $strrealtimequizzes = get_string("modulenameplural", "realtimequiz");
    $strrealtimequiz  = get_string("modulename", "realtimequiz");

    if ($CFG->version < 2007101500) { // < Moodle 1.9
        if ($course->category) {
            $navigation = "<a href=\"../../course/view.php?id=$course->id\">$course->shortname</a> ->";
        } else {
            $navigation = '';
        }

        print_header("$course->shortname: $quiz->name", "$course->fullname",
                     "$navigation <a href=index.php?id=$course->id>$strrealtimequizzes</a> -> $quiz->name", 
                     "", "", true, update_module_button($cm->id, $course->id, $strrealtimequiz), 
                     navmenu($course, $cm));
    } else { // Moodle 1.9
        $navlinks = array();
        $navlinks[] = array('name' => $strrealtimequizzes, 'link' => "index.php?id={$course->id}", 'type' => 'activity');
        $navlinks[] = array('name' => format_string($quiz->name), 'link' => '', 'type' => 'activityinstance');

        $navigation = build_navigation($navlinks);
        
        $pagetitle = strip_tags($course->shortname.': '.$strrealtimequiz.': '.format_string($quiz->name,true));

        print_header_simple($pagetitle, '', $navigation, '', '', true,
                            update_module_button($cm->id, $course->id, $strrealtimequiz), navmenu($course, $cm));
        
    }

    realtimequiz_view_tabs('edit', $cm->id, $context);

    print_box_start('generalbox boxwidthwide boxaligncenter realtimequizbox');
				  
	if (($action == 'doaddquestion') || ($action == 'doeditquestion')) {
	
		if (!confirm_sesskey()) {
			error(get_string('badsesskey', 'realtimequiz'));
		}
	
		if ($addanswers) {
			$minanswers = optional_param('minanswers', 4, PARAM_INT);
			realtimequiz_edit_question($quizid, $questionid, $minanswers + 3);

        } elseif ($canceledit) {
            $action = 'listquestions';
			
		} else {
				
			$question = new stdClass();
			$question->quizid = $quizid;
			$question->questionnum = required_param('questionnum', PARAM_INT);
			$question->questiontext = required_param('questiontext', PARAM_TEXT);
			$question->questiontime = required_param('questiontime', PARAM_INT);
			$answertexts = required_param('answertext', PARAM_TEXT);
			$answercorrects = optional_param('answercorrect', FALSE, PARAM_INT);
			$answerids = required_param('answerid', PARAM_INT);
			$removeimage = optional_param('removeimage', false, PARAM_BOOL);

			// Copy the answers into a suitable array and count how many (valid) correct answers there are
			$correctcount = 0;
			if ($answercorrects !== FALSE) {
				$answers = array();
				$answercount = count($answertexts);
				for ($i=1; $i<=$answercount; $i++) {
					$answers[$i] = new stdClass();
					$answers[$i]->id = $answerids[$i];
					$answers[$i]->answertext = $answertexts[$i];
					$answers[$i]->correct = isset($answercorrects[$i]) ? 1 : 0; // FIX IN CVS
					if ($answers[$i]->correct == 1 && $answers[$i]->answertext != '') {
						$correctcount++;
					}
				}
			}
			
			// Check there is exactly 1 correct answer
			if ($question->questiontext == '') {
				echo '<div class="errorbox">';
				print_string('errorquestiontext','realtimequiz');
				echo '</div>';
				$minanswers = optional_param('minanswers', 4, PARAM_INT);
				realtimequiz_edit_question($quizid, $questionid, $minanswers);
				
			} else if ($correctcount != 1) {
				echo '<div class="errorbox">';
				print_string('onecorrect','realtimequiz');
				echo '</div>';
				$minanswers = optional_param('minanswers', 4, PARAM_INT);
				realtimequiz_edit_question($quizid, $questionid, $minanswers);
				
			} else {
                if ($removeimage && $action == 'doeditquestion') {
                    $q = get_record('realtimequiz_question', 'id', $questionid);
                    if ($q && $q->image) {
                        $fullpath = $CFG->dataroot.'/'.$q->image;
                        if (file_exists($fullpath)) {
                            unlink($fullpath);
                        }
                        $question->image = '';
                    }
                }

				// Update the question
				if ($action == 'doaddquestion') {
					$question->id = insert_record('realtimequiz_question', $question);
				} else {
					$question->id = $questionid;
					update_record('realtimequiz_question', $question);
				}
								
			    if (!$removeimage) {
	                $dir = $course->id.'/'.$CFG->moddata.'/realtimequiz/'.$question->quizid;
	                $fulldir = $CFG->dataroot.'/'.$dir;
	
                    require_once($CFG->dirroot.'/lib/uploadlib.php');
                    $um = new upload_manager('imagefile',false,true,$course,false,$course->maxbytes,true);
                    
                    if ($um->process_file_uploads($fulldir)) {
                        $fp = $um->get_new_filepath();
                        $fn = $um->get_new_filename();
                        if ($fp && $fn) {
                            $size = getimagesize($fp);
                            if ($size) {
                                if ($size[2] == IMAGETYPE_GIF) { $fext = '.gif'; }
                                else if ($size[2] == IMAGETYPE_PNG) { $fext = '.png'; }
                                else if ($size[2] == IMAGETYPE_JPEG) { $fext = '.jpg'; }
                                else { $fext = false; }
                                
                                if ($fext) {
                                    $q = get_record('realtimequiz_question', 'id', $questionid);
                                    if ($q && $q->image) {
                                        if (pathinfo($q->image, PATHINFO_EXTENSION) != $fext) {
                                            if (file_exists($CFG->dataroot.'/'.$q->image)) {
                                                unlink($CFG->dataroot.'/'.$q->image);
                                                // Delete the old file, if it was a different type
                                                // (it will get overwritten below, if it is the same type)
                                            }
                                        }
                                    }
                                
                                    $destname = sprintf('%02d',$question->id).$fext;
                                    $dest = $fulldir.'/'.$destname;
                                    rename($fp, $dest);

                                    $question->image = $dir.'/'.$destname;
                                    update_record('realtimequiz_question', $question);
                                }
                            }
                        }
                    }         
                 }
				
				// Update the answers
				foreach ($answers as $answer) {
					$answer->questionid = $question->id;
				
					if ($answer->id == 0) {	// A new answer to add to the database
						if ($answer->answertext != '') { // Only add it if there is some text there
							insert_record('realtimequiz_answer', $answer, false);
						}
					} else {
						if ($answer->answertext == '') { // Empty answer = remove it
						    delete_records('realtimequiz_submitted', 'answerid', $answer->id); // Delete any submissions for that answer
							delete_records('realtimequiz_answer', 'id', $answer->id);
							
						} else { // Update the answer
							update_record('realtimequiz_answer', $answer);
						}
					}
				}

                if ($saveadd) {
                    $action = 'addquestion';
                } else {
                    $action = 'listquestions';
                }
			}		
		}
	
	} elseif ($action == 'dodeletequestion') {
	
		if (!confirm_sesskey()) {
			error(get_string('badsesskey','realtimequiz'));
		}  

		if (optional_param('yes', false, PARAM_BOOL)) {
		    $answers = get_records('realtimequiz_answer', 'questionid', $questionid);
			if (!empty($answers)) {
			    foreach ($answers as $answer) { // Get each answer for that question
				    delete_records('realtimequiz_submitted', 'answerid', $answer->id); // Delete any submissions for that answer
			    }
			}
		    delete_records('realtimequiz_answer', 'questionid', $questionid); // Delete each answer
			delete_records('realtimequiz_question', 'id', $questionid);
			// Questionnumbers sorted out when we display the list of questions
		}
		
		$action = 'listquestions';
	
	} elseif ($action == 'moveup') {
	
		$thisquestion = get_record('realtimequiz_question','id', $questionid);
		if ($thisquestion) {
			$questionnum = $thisquestion->questionnum;
			if ($questionnum > 1) {
				$swapquestion = get_record('realtimequiz_question','quizid', $quizid, 'questionnum', $questionnum - 1);
				if ($swapquestion) {
					$thisquestion->questionnum = $questionnum - 1;
					$swapquestion->questionnum = $questionnum;
					update_record('realtimequiz_question', $thisquestion);
					update_record('realtimequiz_question', $swapquestion);
				//} else {
					// FIXME? Is it safe just to ignore this?
				}
			}
		//} else {
			// FIXME? Not really that important - can we get away with just ignoring it?
		}
		
		$action = 'listquestions';
	
	} elseif ($action == 'movedown') {
		$thisquestion = get_record('realtimequiz_question','id', $questionid);
		if ($thisquestion) {
			$questionnum = $thisquestion->questionnum;
			$swapquestion = get_record('realtimequiz_question','quizid', $quizid, 'questionnum', $questionnum + 1);
			if ($swapquestion) {
				$thisquestion->questionnum = $questionnum + 1;
				$swapquestion->questionnum = $questionnum;
				update_record('realtimequiz_question', $thisquestion);
				update_record('realtimequiz_question', $swapquestion);
			//} else {
				// FIXME? Is it safe just to ignore this?
			}
		//} else {
			// FIXME? Not really that important - can we get away with just ignoring it?
		}
		
		$action = 'listquestions';
	
	}
	
				  
	switch ($action) {
	
	case 'listquestions':	//Show all the currently available questions
		realtimequiz_list_questions($quizid, $cm);
		break;
	
	case 'addquestion':	// Adding a new question
		realtimequiz_edit_question($quizid);
		break;
	
	case 'editquestion': // Editing the question
		realtimequiz_edit_question($quizid, $questionid);
		break;
		
	case 'deletequestion': // Deleting a question - ask 'Are you sure?'
		realtimequiz_confirm_deletequestion($quizid, $questionid);
		break;
		
	}

    print_box_end();
	
/// Finish the page
    print_footer($course);

?>
