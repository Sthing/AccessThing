/*
 * Demo sketch for the EM4100 library for parsing manchester data from EM4100 chips.
 * Created by Søren Thing Andersen, July 2013.
 * Released into the public domain.
 */
 
 /*
During development I used the following hardware and documentation:
  - Data sheet for the EM4100 RFID chip (the one in the card):
    http://www.digchip.com/datasheets/parts/datasheet/147/EM4100-pdf.php
  - Hardware for communicationg with the RFID chip:
    http://cgi.ebay.com/ws/eBayISAPI.dll?ViewItem&item=251195475040
    Pin 7, Manchester out, is connected to Arduino Pin 8.
  - Data sheet for the ATmega328P micro controller:
    http://www.atmel.com/Images/doc8161.pdf
    
Ressources used:
  Timer/Counter 1
  ICP1 alias Arduino pin 8.
  Interrupt services for capture and overflow on the timer.

Uncomment the line "#define DEBUG_EM4100" IN THE FILE EM4100.c to get lots of debug on Serial.
*/

#include <EM4100.h>

EM4100::State state;
byte cardFacility;      // First 8 bits of payload
unsigned long cardUid; 	// Last 32 bits of payload

void setup() {
  Serial.begin(9600);
  Serial.println("Connect manchester input to pin 8 and scan card...");
  // Start the interrups and the stet machine.
  Em4100.startRfidCapture();
}

void loop() {
  state = Em4100.getState();
  
  if (0 && state == EM4100::STATE_CAPTURE) { // Enable for even more info
    Serial.print("Capturing: ");      
    Em4100.dumpCaptureInfo(); // Show info about the timing discovered and the number of bit periods captured.
    delay(100);
  }

  if (state == EM4100::STATE_DONE) {
    // Em4100.dumpCaptureData(); // Enable for a dump of the captured manchester levels.
    if (Em4100.parseCaptureData()) {
      cardFacility = Em4100.getCardFacility();
      cardUid      = Em4100.getCardUid();
      // Dump detailed card info
      Serial.print("Decoded data. Facility: 0x");
      Serial.print(cardFacility, HEX);
      Serial.print(" UID: 0x");
      Serial.print(cardUid, HEX); // All 32 bits in hexadecimal
      Serial.print(" = ");
      Serial.print(cardUid); // All 32 bits in decimal one number
      Serial.print(" (");
      Serial.print(cardUid >> 16); // Upper 16 bits in decimal
      Serial.print(",");
      Serial.print(cardUid & 0xFFFF); // Lower 16 bits in decimal
      Serial.println(")");
    }
    // Start capturing again.
    Em4100.startRfidCapture();
  }
} // End loop()


