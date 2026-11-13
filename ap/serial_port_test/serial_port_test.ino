// AP board — শুধু USB Serial টেস্ট (কোনো HaLow / I2S নেই)
// Arduino: ফোল্ডার খুলুন → ap/serial_port_test/
// Tools → USB CDC On Boot → Enabled (LilyGO S3)
// Monitor: 115200
// প্রতি ৫০০ms লাইন; টাইপ করে Enter দিলে echo হয়।

#include <Arduino.h>

static uint32_t seq = 0;

void setup() {
  Serial.begin(115200);
  Serial.setTxTimeoutMs(0);
  delay(800);
  Serial.println();
  Serial.println("=== AP serial_port_test ===");
  Serial.println("115200 | data hole COM port-e asbe");
  Serial.flush();
}

void loop() {
  while (Serial.available()) {
    String line = Serial.readStringUntil('\n');
    line.trim();
    Serial.printf("ECHO: %s\n", line.c_str());
  }

  Serial.print("HB millis=");
  Serial.print(millis());
  Serial.print(" seq=");
  Serial.println(seq++);
  delay(500);
}
