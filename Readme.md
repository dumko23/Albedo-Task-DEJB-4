# Albedo-Test-Task №4
ALbedo Internship Task 


### How to run this project?
```sample.env.example``` is located in project root folder. Create your own ```.env``` file to adjust project configurations.



Adjust following section to configurate your DB connection:
```
DB_HOST=mysql
...
```


Adjust following section to configure number of process threads:
```
THREAD_NUM=2
```


Adjust following section to configure debug mode ("OFF" to disable, "ON" to enable) and number of delay seconds:
```
DEBUG_MODE="OFF"
DEBUG_DELAY=1
```


FRESH_PARSE - when set to 'true' - creates fresh tables (BE CAREFUL: it'll drop tables if already exists); when set to 'false' - doing parse processes.
```
FRESH_PARSE=true
```


If you running project with Docker - in project root terminal write ```docker-compose build``` and ```docker-compose up -d``` to create and run project docker container in detached mode. Now your project is running parse and writing logs into ```App/classes/logger/logs``` or in your containers terminal.


If you running project from server - just run ```php index.php``` from App folder and expect the same result as written above. 
