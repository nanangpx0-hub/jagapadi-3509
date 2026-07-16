<?php

require_once ROOT_PATH . '/app/controllers/Api/BaseApiController.php';

class StorytellingController extends BaseApiController {
    public function getAnalyses() {
        $this->notImplemented('Storytelling analyses endpoint');
    }

    public function getAnalysis($id) {
        $this->notImplemented('Storytelling analysis detail endpoint');
    }

    public function generateAnalysis() {
        $this->notImplemented('Storytelling generate endpoint');
    }

    public function saveAnalysis() {
        $this->notImplemented('Storytelling save endpoint');
    }

    public function publishAnalysis($id) {
        $this->notImplemented('Storytelling publish endpoint');
    }

    public function getChartData() {
        $this->notImplemented('Storytelling chart data endpoint');
    }

    public function getStats() {
        $this->notImplemented('Storytelling stats endpoint');
    }
}
