<?php

/**
 * This is the model class for table "interview".
 *
 * The followings are the available columns in table 'interview':
 * @property integer $id
 * @property integer $random_key
 * @property integer $active
 * @property integer $studyId
 * @property integer $completed
 */
class Interview extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Interview the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'interview';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('studyId', 'required'),
			array('id, active, studyId, completed', 'length', 'max'=>255),
			array('id, active, studyId', 'numerical', 'integerOnly'=>true),
			array('completed','default','value'=>0),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('id, active, studyId, completed', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'random_key' => 'Random Key',
			'active' => 'Active',
			'studyId' => 'Study',
			'completed' => 'Completed',
		);
	}

	// CORE FUNCTION
	public function getLastUnanswered($id){
		$model = Interview::model()->findByPk($id);
		$study = Study::model()->findByPk($model->studyId);
		$pages = Study::buildQuestions($model, null, $id);
		for($i=0; $i < count($pages); $i++){
			foreach($pages[$i] as $question){
				if($question->answerType == "ALTER_PROMPT"){
					if(count(Alters::model()->findAllByAttributes(array('interviewId'=>$id))) < $study->minAlters)
						return $i;
				}else if($question->answerType != "ALTER_PROMPT"){
					if($question->subjectType != "ALTER" && $question->subjectType != "ALTER_PAIR")
						$answer = Answer::model()->findByAttributes(array('studyId'=>$question->studyId, 'interviewId'=>$id, 'questionId'=>$question->id));
					else
						$answer = Answer::model()->findByAttributes(array('studyId'=>$question->studyId, 'interviewId'=>$id, 'questionId'=>$question->id, 'alterId1'=>$question->alterId1, 'alterId2'=>$question->alterId2));
					if(!$answer)
						return $i;
				}
			}
		}
	}


	public function getInterviewFromEmail($studyId, $email){
		$interviewId = q("SELECT interviewId FROM answer WHERE value='$email' AND questionType = 'EGO_ID' AND studyId = $studyId")->queryScalar();
		if($interviewId)
			return Interview::model()->findByPk($interviewId);
		else
			return false;
	}

	public function countAlters($id){
		$criteria=array(
			'condition'=>"FIND_IN_SET(" . $id .", interviewId)",
		);
		$models = Alters::model()->findAll($criteria);
		return count($models);
	}

	public function getRespondant($id){
		$studyId = q("SELECT studyId FROM answer WHERE interviewId = $id")->queryScalar();

		if(!$studyId)
			return 'error';
		$firstId = q("SELECT id from question WHERE studyId = $studyId and subjectType = 'EGO_ID' ORDER by ordering")->queryScalar();

		if(!$firstId)
			return '';
		$egoIdAnswer = Answer::model()->find(array(
			'condition'=>"interviewId=:interviewId AND questionId = $firstId AND value != ''",
			'params'=>array(':interviewId'=>$id),
		));

		if(isset($egoIdAnswer->value) && stristr($egoIdAnswer->value, '@'))
			return q("SELECT name FROM alterList WHERE email = '" .$egoIdAnswer->value . "'")->queryScalar();
		else if(isset($egoIdAnswer->value))
			return $egoIdAnswer->value;
		else
			return '';
	}

	public function getEgoId($id){
		$interview = q("SELECT * FROM interview where id = $id")->queryRow();
		$ego_id_questions = q("SELECT * FROM question WHERE subjectType = 'EGO_ID' AND studyId = " . $interview['studyId'] . " ORDER BY ordering")->queryAll();
		$egoId = "";
		foreach ($ego_id_questions as $question){
			$headers[] = $question['title'];
		}
		$ego_ids = array();
		foreach ($ego_id_questions as $question){
			if($question['answerType'] == "MULTIPLE_SELECTION")
				$ego_ids[] = q("SELECT name FROM questionOption WHERE questionId = " . $question['id'])->queryScalar();
			else
				$ego_ids[] = q("SELECT value FROM answer WHERE interviewId = " . $interview['id']  . " AND questionId = " . $question['id'])->queryScalar();
		}
		if(isset($ego_ids))
			$egoId = implode("_", $ego_ids);
		return $egoId;
	}

	// CORE FUNCTION
	public function interpretTags($string, $interviewId = null, $alterId1 = null, $alterId2 = null,  $study = null)
	{

		if(!$interviewId)
			return $string;

		if($study == null){
			$studyId = q("SELECT studyId FROM interview WHERE id = ".$interviewId)->queryScalar();
			$study = Study::model()->findByPk($studyId);
		}

		if($study->multiSessionEgoId){
			$egoValue = q("SELECT value FROM answer WHERE interviewId = " . $interviewId . " AND questionID = " . $study->multiSessionEgoId)->queryScalar();
			$multiIds = q("SELECT id FROM question WHERE title = (SELECT title FROM question WHERE id = " . $study->multiSessionEgoId . ")")->queryColumn();
			$studyIds = q("SELECT id FROM study WHERE multiSessionEgoId in (" . implode(",", $multiIds) . ")")->queryColumn();
			$interviewIds = q("SELECT interviewId FROM answer WHERE questionId in (" . implode(",", $multiIds) . ") AND value = '" .$egoValue . "'" )->queryColumn();
			$interviewId = implode(",", $interviewIds);
			$studyId = $studyIds;
		}

		// parse out and replace variables
		preg_match('#<VAR (.+?) />#ims', $string, $vars);
		foreach($vars as $var){
			$question = Question::model()->findByAttributes(array('title'=>$var, 'studyId'=>$studyId));
			if($question){
				if($interviewId != null){
					$end = " AND interviewId in (". $interviewId .")";
				}else{
					$end = "";
				}
				$criteria=new CDbCriteria;
				$criteria=array(
					'condition'=>"questionId = " . $question->id . $end,
					'order'=>'id DESC',
				);
				$lastAnswer = Answer::model()->find($criteria);
			}
			if(isset($lastAnswer)){
				if($question->answerType == "SELECTION" || $question->answerType == "MULTIPLE_SELECTION"){
					$option = QuestionOption::model()->findbyPk($lastAnswer->value);
					if($option){
						$lastAnswer->value = $option->name;
					}else{
						$lastAnswer->value = "";
					}
				}
				$string =  preg_replace('#<VAR '.$var.' />#', $lastAnswer->value, $string);
			}else{
				$string =  preg_replace('#<VAR '.$var.' />#', '', $string);
			}
		}

		// performs calculations on questions
		preg_match_all('#<CALC (.+?) />#ims', $string, $calcs);
		foreach($calcs[1] as $calc){
			preg_match('/(\w+)/', $calc, $vars);
			foreach($vars as $var){
				$question = Question::model()->findByAttributes(array('title'=>$var, 'studyId'=>$studyId));
				if($question){
					if($interviewId != null){
						$end = " AND interviewId in (". $interviewId . ")";
					}else{
						$end = "";
					}
					$criteria=new CDbCriteria;
					$criteria=array(
						'condition'=>"questionId = " . $question->id . $end,
						'order'=>'id DESC',
					);
					$lastAnswer = Answer::model()->find($criteria);
				}
				if(isset($lastAnswer))
					$logic =  preg_replace('#'.$var.'#', $lastAnswer->value, $calc);
				else
					$logic =  preg_replace('#'.$var.'#', '', $calc);
			}
			$logic = 'return ' . $logic . ';';

			$calculation = eval($logic);
			$string =  str_replace("<CALC ".$calc." />", $calculation, $string);
		}

		// counts numbers of times question is answered with string
		preg_match_all('#<COUNT (.+?) />#ims', $string, $counts);
		foreach($counts[1] as $count){
			list($qTitle, $answer) = preg_split('/\s/', $count);
			$answer = str_replace ('"', '', $answer);
			$question = Question::model()->findByAttributes(array('title'=>$qTitle, 'studyId'=>$studyId));
			$criteria=new CDbCriteria;
			if(!$question)
				continue;
			if($question->answerType == "SELECTION" || $question->answerType == "MULTIPLE_SELECTION"){
				$option = QuestionOption::model()->findbyAttributes(array('name'=>$answer, 'questionId'=>$question->id));
				if(!$option)
					continue;
				if($interviewId != null){
					$end = " AND interviewId in (". $interviewId. ")";
				}else{
					$end = "";
				}
				$criteria=array(
					'condition'=>'questionId = '. $question->id .' AND FIND_IN_SET('. $option->id .' ,value)' . $end,
				);
			}else{
				$criteria=array(
					'condition'=>'value = "' . $answer . '"' . $end,
				);
			}
			$answers = Answer::model()->findAll($criteria);
			$string =  str_replace("<COUNT ".$count." />", count($answers), $string);
		}


		// same as count, but limited to specific alter / alter pair questions
		preg_match_all('#<CONTAINS (.+?) />#ims', $string, $containers);
		foreach($containers[1] as $contains){
			list($qTitle, $answer) = preg_split('/\s/', $contains);
			$answer = str_replace ('"', '', $answer);
			$question = Question::model()->findByAttributes(array('title'=>$qTitle, 'studyId'=>$studyId));
			$criteria=new CDbCriteria;
			if(!$question)
				continue;
			if($interviewId != null){
				$end = " AND interviewId in (". $interviewId . ")";
				if(is_numeric($alterId1))
					$end .= " AND alterId1 = " . $alterId1;
				if(is_numeric($alterId2))
					$end .= " AND alterId2 = " . $alterId2;
			}else{
				$end = "";
			}
			if($question->answerType == "SELECTION" || $question->answerType == "MULTIPLE_SELECTION"){
				$option = QuestionOption::model()->findbyAttributes(array('name'=>$answer, 'questionId'=>$question->id));
				if(!$option)
					continue;
				$criteria=array(
					'condition'=>'questionId = '. $question->id .' AND FIND_IN_SET('. $option->id .' ,value)' . $end,
				);
			}else{
				$criteria=array(
					'condition'=>'value = "' . $answer . '"' . $end,
				);
			}
			$theAnswer = Answer::model()->find($criteria);
			$string =  str_replace("<CONTAINS ".$contains." />", count($theAnswer), $string);
		}

		// parse out and show logics
		preg_match_all('#<IF (.+?) />#ims', $string, $showlogics);
		//print_r($showlogics);
		foreach($showlogics[1] as $showlogic){
			preg_match('/(.+?) (==|!=|<|>|<=|>=)+ (.+?) \"(.+?)\"/ims', $showlogic, $exp);
			if(count($exp) > 1){
				for($i = 1; $i < 3; $i++){
					if($i == 2 || is_numeric($exp[$i]))
						continue;
					if(preg_match("#/>#", $exp[$i])){
						$exp[$i] = Interview::interpretTags($exp[$i]);
					}else{
						$question = Question::model()->findByAttributes(array('title'=>$exp[$i], 'studyId'=>$studyId));
						if(!$question){
							$exp[$i] = "";
							continue;
						}
						if($interviewId != null){
							$end = " AND interviewId in (". $interviewId .")";
						}else{
							$end = "";
						}
						$criteria=new CDbCriteria;
						$criteria=array(
							'condition'=>"questionId = " . $question->id . $end,
							'order'=>'id DESC',
						);
						$lastAnswer = Answer::model()->find($criteria);
						$exp[$i] = $lastAnswer->value;
					}
				}
				$logic = 'return ' . $exp[1] . ' ' . $exp[2] . ' ' . $exp[3] . ';';
				if($exp[1] && $exp[2] && $exp[3])
					$show = eval($logic);
				else
					$show = false;
				if($show){
					$string =  str_replace("<IF ".$showlogic." />", $exp[4], $string);
				}else{
					$string =  str_replace("<IF ".$showlogic." />", "", $string);
				}
			}
		}
		return $string;
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		// Warning: Please modify the following code to remove attributes that
		// should not be searched.

		$criteria=new CDbCriteria;

		$criteria->compare('id',$this->id);
		$criteria->compare('active',$this->active);
		$criteria->compare('studyId',$this->studyId);
		$criteria->compare('completed',$this->completed);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
}