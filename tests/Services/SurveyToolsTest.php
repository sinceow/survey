<?php

namespace Jobsys\Survey\Tests\Services;

use Jobsys\Survey\Services\SurveyService;
use PHPUnit\Framework\TestCase;

class SurveyToolsTest extends TestCase
{
    public function test_export_survey_word()
    {
        $service = new SurveyService();
        list($file_path, $error) = $service->exportSurveyAsWord(10, __DIR__ . DIRECTORY_SEPARATOR . 'files');
        $this->assertNull($error);
    }

    public function test_survey_statistics()
    {
        $service = new SurveyService();
        list($result, $error) = $service->getSurveyStatistics(21);
        $this->assertNull($error);
    }


    public function test_survey_statistics_detail()
    {
        $service = new SurveyService();
        list($result, $error) = $service->getSurveyStatisticsDetail(4);
        $this->assertNull($error);
    }

    public function test_export_survey_statistics()
    {
        $service = new SurveyService();
        list($result, $error) = $service->exportSurveyStatistics(4, __DIR__ . DIRECTORY_SEPARATOR . 'files');
        $this->assertNull($error);
    }

    public function test_import_survey()
    {
        $service = new SurveyService();
        list($result, $error) = $service->importSurvey("C:\\Users\\since\\Desktop\\import_tester.xlsx", [
            'title' => '测试导入',
        ], [
            'question_start_index' => 'G',
            'question_end_index' => 'V',
            'question_order_regex' => '/^(\d+)/',
            'question_title_regex' => '/、(\S+)$/',
        ], [
            'option_separator' => '┋',
            'option_fillable_start_regex' => '〖',
            'option_fillable_end_regex' => '〗',
        ], [
            '来自IP' => 'ip',
            '提交答卷时间' => 'created_at',
            '1、学号：' => 'user_id'
        ], [
            'datetime_format' => 'Y/m/d H:i:s',
            'skip_holder' => '(跳过)'
        ]);
        $this->assertNull($error);
    }
}