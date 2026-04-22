<?php

require_once ROOT_PATH . '/app/controllers/Api/BaseApiController.php';

class IoTController extends BaseApiController {
    public function getSensors() {
        $this->notImplemented('IoT sensors endpoint');
    }

    public function getActuators() {
        $this->notImplemented('IoT actuators endpoint');
    }

    public function getLogs() {
        $this->notImplemented('IoT logs endpoint');
    }

    public function updateSensor($id) {
        $this->notImplemented('IoT sensor update endpoint');
    }

    public function controlActuator($id) {
        $this->notImplemented('IoT actuator control endpoint');
    }

    public function getRealtimeSensors() {
        $this->notImplemented('IoT realtime sensors endpoint');
    }

    public function getSchedule() {
        $this->notImplemented('IoT schedule endpoint');
    }

    public function updateSchedule() {
        $this->notImplemented('IoT schedule update endpoint');
    }
}
