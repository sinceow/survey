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
        list($result, $error) = $service->getSurveyStatisticsDetail(21);
        $this->assertNull($error);
    }

    public function test_export_survey_statistics()
    {
        $service = new SurveyService();
        list($result, $error) = $service->exportSurveyStatistics(21, __DIR__ . DIRECTORY_SEPARATOR . 'files');
        $this->assertNull($error);
    }
}