/*
 * EM4100.h - Library for parsing manchester data from EM4100 chips.
 * Created by Søren Thing Andersen, July 2013.
 * Released into the public domain.
 *
 * Please see the examples/demo sketch for background and usage.
 */

#ifndef EM4100_h
#define EM4100_h

#include "Arduino.h"

class EM4100 {
  public:
		enum State {
			STATE_INIT,
			STATE_FIND_PERIOD,
			STATE_CAPTURE,
			STATE_DONE
		};
    EM4100();
		State getState();
		void startRfidCapture();
    void onTimerOverflow();
		void onTimerCapture();
		void dumpCaptureInfo();
		void dumpCaptureData();
		boolean parseCaptureData();
		byte getCardFacility();
		unsigned long getCardUid();

  private:
		enum { // Constants:
			inputCapturePin		= 8,		// ICP1 alias Arduino pin 8
			// Setup for Timer1 prescaler
			prescale					= 64,		// prescale factor (each tick 4 us @16MHz)
			prescaleBits 			= B011,	// see Table 15-5 in the data sheet.
			// Buffer sizes
			codeLength				= 64,		// No of bits in the RFID code.
			captureSize				= 1 + 2 * codeLength / 4,	// 4 bit periods in each byte. Capture at least 2 x codeLength bit periods (we do not know where the start is, allow for phase offset of one bit)
			bitBufferSize 		= 2 * codeLength / 8,			// Room for 2 x codeLength bits
		};

		// Variables used by the state machine capturing the data
		volatile State state;
		volatile word bitPeriod;
		volatile byte count;
		volatile word shortMin;
		volatile word shortMax;
		volatile word longMin;
		volatile word longMax;
		volatile word discarded;									// Set when a transition length is discarded as invalid
		volatile byte captureData[captureSize];		// Each byte contains data for 4 full bit periods.
		volatile byte bitCount;

		// Variables used during decoding
		byte bitBuffer[bitBufferSize];						// Each byte contains data for 4 full bit periods. LSB is first bit.
		byte cardFacility;      									// First 8 bits of payload
		unsigned long cardUid; 									  // Last 32 bits of payload
		byte columnSums[4];     									// Used for parity calculation of the four columns

		boolean parseBitBuffer();
		boolean parseNibble(word offset, byte *nibble);

};
extern EM4100 Em4100;
#endif
