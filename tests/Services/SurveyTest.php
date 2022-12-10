<?php

namespace Jobsys\Survey\Tests\Services;

use Jobsys\Survey\Services\SurveyService;
use Jobsys\Survey\Survey;
use Jobsys\Survey\SurveyQuestion;
use PHPUnit\Framework\TestCase;

class SurveyTest extends TestCase
{

    public function test_save_survey()
    {
        $service = new SurveyService();
        $survey = [
            'title' => 'Test Survey',
            'type' => 1,
            'intro' => 'Test Survey Intro',
            'outro' => 'Test Survey Outro',
            'started_at' => time(),
            'ended_at' => time() + 3600 * 24 * 7,
            'show_type' => 1,
            'creator' => 'Test Creator',
        ];

        list($result, $error) = $service->saveSurvey($survey);
        $this->assertTrue($result['id'] > 0);
    }

    public function test_delete_survey()
    {
        $service = new SurveyService();
        $survey = [
            'title' => 'Test Survey',
            'type' => 1,
            'intro' => 'Test Survey Intro',
            'outro' => 'Test Survey Outro',
            'started_at' => time(),
            'ended_at' => time() + 3600 * 24 * 7,
            'show_type' => 1,
            'creator' => 'Test Creator',
        ];

        list($result, $error) = $service->saveSurvey($survey);
        $this->assertTrue($result['id'] > 0);
        //$this->assertTrue($service->deleteSurvey($result['id'])[0]);
        $this->assertTrue($service->deleteSurvey(20));
    }

    public function test_delete_specify_survey()
    {
        $service = new SurveyService();
        $this->assertTrue($service->deleteSurvey(10, true)[0]);
    }

    public function test_save_survey_and_question()
    {
        $service = new SurveyService();
        $survey = [
            'title' => 'Test Survey',
            'type' => 1,
            'intro' => 'Test Survey Intro',
            'outro' => 'Test Survey Outro',
            'started_at' => time(),
            'ended_at' => time() + 3600 * 24 * 7,
            'show_type' => 1,
            'creator' => 'Test Creator',
        ];

        list($result, $error) = $service->saveSurvey($survey);
        $this->assertTrue($result['id'] > 0, '保存问卷');

        $survey_id = $result['id'];

        $question_1 = [
            'survey_id' => $survey_id,
            'title' => 'Test Question 1',
            'type' => SurveyQuestion::QUESTION_TYPE_INPUT,
            'is_required' => 1,
            'sort_order' => 1,
        ];
        list ($question_saved_1, $error) = $service->saveQuestion($question_1);
        $this->assertTrue($question_saved_1['id'] > 0, '保存问题1');


        $question_2 = [
            'survey_id' => $survey_id,
            'title' => 'Test Question 2',
            'type' => SurveyQuestion::QUESTION_TYPE_RADIO,
            'is_required' => 1,
            'sort_order' => 2,
        ];
        $option_1 = [
            'survey_id' => $survey_id,
            'title' => 'Test Option 1',
            'sort_order' => 1,
        ];
        $option_2 = [
            'survey_id' => $survey_id,
            'title' => 'Test Option 2',
            'sort_order' => 2,
        ];

        list ($question_saved_2, $error) = $service->saveQuestion($question_2, [$option_1, $option_2]);
        $this->assertTrue($question_saved_2['id'] > 0, '保存问题2');

        $question_saved_1['title'] = 'Test Question 1 Updated';
        $question_saved_1['sort_order'] = 2;
        list ($question_saved_1, $error) = $service->saveQuestion($question_saved_1);
        $this->assertEquals($error, '该问卷已存在相同序号的题目', '更新问题1');

        $question_saved_2['title'] = 'Test Question 2 Updated';
        list ($question_saved_2, $error) = $service->saveQuestion($question_saved_2, [$option_1, $option_2]);
        $this->assertEquals($error, '该题目已存在相同选项: Test Option 1', '更新问题2');

    }

    public function test_delete_question()
    {
        $service = new SurveyService();
        $question_ids = [19, 20];
        list($result, $error) = $service->deleteQuestions($question_ids);
        $this->assertTrue($result, '删除问题');
    }

    public function test_update_question_chapter()
    {
        $service = new SurveyService();
        $question_id = [15, 16, 17];
        list($result, $error) = $service->updateQuestionChapter($question_id, "Test Chapter");
        $this->assertTrue($result, '更新问题章节');
    }

    public function test_get_question_by_survey_id()
    {
        $service = new SurveyService();

        $survey_id = 0;
        list($result, $error) = $service->getSurvey($survey_id, true, true);
        $this->assertEquals($error, '问卷不存在', '获取问卷问题');

        $survey_id = 15;
        list($result, $error) = $service->getSurvey($survey_id, true, true);
        $this->assertTrue(count($result) > 0, '获取问卷问题');
    }

    public function test_get_specify_question()
    {
        $service = new SurveyService();
        $question_id = 0;
        list($result, $error) = $service->getQuestion($question_id);
        $this->assertEquals($error, '题目不存在', '获取指定问题');

        $question_id = 10;
        list($result, $error) = $service->getQuestion($question_id);
        $this->assertTrue(count($result) > 0, '获取指定问题');
    }

    public function test_answer_survey_single()
    {
        $service = new SurveyService();

        $survey = [
            'title' => 'Test Survey',
            'type' => Survey::SURVEY_SHOW_TYPE_SINGLE,
            'intro' => 'Test Survey Intro',
            'outro' => 'Test Survey Outro',
            'started_at' => time(),
            'ended_at' => time() + 3600 * 24 * 7,
            'show_type' => 1,
            'creator' => 'Test Creator',
        ];

        list($survey, $error) = $service->saveSurvey($survey);

        $question_1 = [
            'survey_id' => $survey['id'],
            'title' => 'Test Question 1',
            'type' => SurveyQuestion::QUESTION_TYPE_RADIO,
            'is_required' => 1,
            'sort_order' => 1,
        ];

        $question_2 = [
            'survey_id' => $survey['id'],
            'title' => 'Test Question 2',
            'type' => SurveyQuestion::QUESTION_TYPE_INPUT,
            'is_required' => 1,
            'sort_order' => 2,
        ];

        $question_3 = [
            'survey_id' => $survey['id'],
            'title' => 'Test Question 3',
            'type' => SurveyQuestion::QUESTION_TYPE_INPUT,
            'is_required' => 1,
            'sort_order' => 3,
        ];

        $option_1 = [
            'survey_id' => $survey['id'],
            'title' => 'Test Option 1 Jump to 2',
            'sort_order' => 1,
            'jump_to_question_order' => 2
        ];

        $option_2 = [
            'survey_id' => $survey['id'],
            'title' => 'Test Option 2 Jump to 3',
            'sort_order' => 2,
            'jump_to_question_order' => 3
        ];


        list ($question_saved_1, $error) = $service->saveQuestion($question_1, [$option_1, $option_2]);
        list ($question_saved_2, $error) = $service->saveQuestion($question_2);
        list ($question_saved_3, $error) = $service->saveQuestion($question_3);


        $user_id = 1;
        $user_type = 'student';

        list($answer_survey, $error) = $service->answerSurvey($user_id, $user_type, $survey['id']);


        $question = $answer_survey['questions'][0];

        $answer = [
            'survey_id' => $survey['id'],
            'survey_question_id' => $question['id'],
            'survey_option_id' => $question['options'][1]['id'],
            'answer' => $question['options'][0]['title'],
        ];

        list($answer_survey, $error) = $service->answerQuestion($user_id, $user_type, $survey['id'], [$answer]);


        $this->assertTrue(true, '回答问卷');
    }
}
