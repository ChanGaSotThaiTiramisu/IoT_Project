#include <bluefruit.h>
#include <nrf_gpio.h>

// Nút nối giữa chân GPIO và GND (dùng PULLUP).
#define BUTTON_A NRF_GPIO_PIN_MAP(0, 17)
#define BUTTON_B NRF_GPIO_PIN_MAP(0, 20)
#define BUTTON_C NRF_GPIO_PIN_MAP(0, 22)
#define BUTTON_D NRF_GPIO_PIN_MAP(0, 24)
#define BUTTON_SUBMIT NRF_GPIO_PIN_MAP(1, 0)

#define LED_GREEN NRF_GPIO_PIN_MAP(0, 6)
#define LED_YELLOW NRF_GPIO_PIN_MAP(1, 6)

// Phải trùng device_code trong bảng devices của MariaDB.
#define CLIENT_ID "NRF001"

BLEUart bleuart;

bool previousConnection = false;
bool idSent = false;
bool accepted = false;
bool questionOpen = false;
bool waitingAck = false;

unsigned long currentQuestionId = 0;
char selectedAnswer = '\0';
String receiveBuffer = "";


void startAdvertising() {
  Bluefruit.Advertising.addFlags(
    BLE_GAP_ADV_FLAGS_LE_ONLY_GENERAL_DISC_MODE
  );
  Bluefruit.Advertising.addTxPower();
  Bluefruit.Advertising.addService(bleuart);
  Bluefruit.ScanResponse.addName();
  Bluefruit.Advertising.restartOnDisconnect(true);
  Bluefruit.Advertising.setInterval(32, 244);
  Bluefruit.Advertising.start(0);
}


void clearQuestion() {
  questionOpen = false;
  waitingAck = false;
  currentQuestionId = 0;
  selectedAnswer = '\0';
  nrf_gpio_pin_clear(LED_YELLOW);
}


void resetSession() {
  idSent = false;
  accepted = false;
  clearQuestion();
  nrf_gpio_pin_clear(LED_GREEN);
}


void sendClientId() {
  if (!Bluefruit.connected() || !bleuart.notifyEnabled()) {
    return;
  }

  // Ghép thành một gói rồi gửi một lần
  String idPacket = "ID:";
  idPacket += CLIENT_ID;

  bleuart.println(idPacket);
  idSent = true;

  Serial.print("Sent packet: ");
  Serial.println(idPacket);
}


void processCommand(String command) {
  command.trim();

  if (command.length() == 0) {
    return;
  }

  Serial.print("Gateway -> nRF52840: ");
  Serial.println(command);

  if (command == "REQUEST_ID") {
    sendClientId();
  }
  
  else if (command == "ACCEPT") {
    accepted = true;
    clearQuestion();
    nrf_gpio_pin_set(LED_GREEN);
    Serial.println("Device accepted by web gateway");
  }
  else if (command == "REJECT") {
    Serial.println("Device rejected by web gateway");
    resetSession();
    delay(100);
    if (Bluefruit.connected()) {
      Bluefruit.disconnect(Bluefruit.connHandle());
    }
  }
  else if (command.startsWith("QUESTION:")) {
    unsigned long questionId = command.substring(9).toInt();

    if (accepted && questionId > 0) {
      currentQuestionId = questionId;
      questionOpen = true;
      waitingAck = false;
      selectedAnswer = '\0';
      
      nrf_gpio_pin_clear(LED_YELLOW);

      Serial.print("Question opened: ");
      Serial.println(currentQuestionId);
    }
  }
  else if (command == "ACK") {
    if (accepted && waitingAck) {
      Serial.println("Answer saved by web server");
      clearQuestion();
    }
  }
  else if (command == "RETRY") {
    if (accepted && currentQuestionId > 0) {
      questionOpen = true;
      waitingAck = false;
      selectedAnswer = '\0';
      nrf_gpio_pin_clear(LED_YELLOW);
      Serial.println("Please select and submit again");
    }
  }
  else if (command == "CLOSE") {
    clearQuestion();
    Serial.println("Question closed by web server");
  }
  else if (command == "DISCONNECT") {
    resetSession();
    delay(100);
    if (Bluefruit.connected()) {
      Bluefruit.disconnect(Bluefruit.connHandle());
    }
  }
}


void receiveFromGateway() {
  while (bleuart.available()) {
    char data = (char)bleuart.read();

    if (data == '\n') {
      processCommand(receiveBuffer);
      receiveBuffer = "";
    }
    else if (data != '\r') {
      receiveBuffer += data;

      if (receiveBuffer.length() > 80) {
        receiveBuffer = "";
      }
    }
  }
}


bool buttonPressed(uint32_t pin) {
  if (nrf_gpio_pin_read(pin) != 0) {
    return false;
  }

  delay(30);
  if (nrf_gpio_pin_read(pin) != 0) {
    return false;
  }

  while (nrf_gpio_pin_read(pin) == 0) {
    receiveFromGateway();
    delay(10);
  }

  return true;
}


void selectAnswer(char answer) {
  selectedAnswer = answer;
  Serial.print("Selected: ");
  Serial.println(selectedAnswer);
}


void submitAnswer() {
  if (!accepted || !questionOpen || waitingAck) {
    Serial.println("Cannot submit: no open question");
    return;
  }

  if (selectedAnswer == '\0') {
    Serial.println("Please select A, B, C or D");
    return;
  }

 // Ghép thành một gói hoàn chỉnh
  String answerPacket = "ANS:";
  answerPacket += String(currentQuestionId);
  answerPacket += ":";
  answerPacket += selectedAnswer;

  // Chỉ gửi một lần qua BLE
  bleuart.println(answerPacket);

  Serial.print("Sent packet: ");
  Serial.println(answerPacket);

  questionOpen = false;
  waitingAck = true;

  nrf_gpio_pin_clear(LED_YELLOW);
}


void updateYellowLed() {
  static unsigned long lastToggle = 0;
  static bool ledState = false;

  bool shouldBlink = accepted
    && questionOpen
    && !waitingAck;
    //&& selectedAnswer != '\0';

  if (!shouldBlink) {
    ledState = false;
    nrf_gpio_pin_clear(LED_YELLOW);
    return;
  }

  if (millis() - lastToggle >= 250) {
    lastToggle = millis();
    ledState = !ledState;

    if (ledState) {
      nrf_gpio_pin_set(LED_YELLOW);
    } else {
      nrf_gpio_pin_clear(LED_YELLOW);
    }
  }
}


void setup() {
  Serial.begin(115200);
  delay(500);

  nrf_gpio_cfg_input(BUTTON_A, NRF_GPIO_PIN_PULLUP);
  nrf_gpio_cfg_input(BUTTON_B, NRF_GPIO_PIN_PULLUP);
  nrf_gpio_cfg_input(BUTTON_C, NRF_GPIO_PIN_PULLUP);
  nrf_gpio_cfg_input(BUTTON_D, NRF_GPIO_PIN_PULLUP);
  nrf_gpio_cfg_input(BUTTON_SUBMIT, NRF_GPIO_PIN_PULLUP);

  nrf_gpio_cfg_output(LED_GREEN);
  nrf_gpio_cfg_output(LED_YELLOW);
  nrf_gpio_pin_clear(LED_GREEN);
  nrf_gpio_pin_clear(LED_YELLOW);

  Bluefruit.begin();
  Bluefruit.setTxPower(4);
  Bluefruit.setName("Quiz_NRF52840");

  bleuart.begin();
  startAdvertising();

  Serial.println("BLE web quiz ready");
}


void loop() {
  bool connected = Bluefruit.connected();

  if (connected && !previousConnection) {
    Serial.println("BLE connected");
    resetSession();
  }

  if (!connected && previousConnection) {
    Serial.println("BLE disconnected");
    resetSession();
  }

  previousConnection = connected;

  // if (connected && bleuart.notifyEnabled() && !idSent) {
  //   sendClientId();
  // }

  receiveFromGateway();

  if (accepted && questionOpen && !waitingAck) {
    if (buttonPressed(BUTTON_A)) selectAnswer('A');
    if (buttonPressed(BUTTON_B)) selectAnswer('B');
    if (buttonPressed(BUTTON_C)) selectAnswer('C');
    if (buttonPressed(BUTTON_D)) selectAnswer('D');
    if (buttonPressed(BUTTON_SUBMIT)) submitAnswer();
  }

  updateYellowLed();
  delay(5);
}
