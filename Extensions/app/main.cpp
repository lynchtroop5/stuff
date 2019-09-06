#include <iostream>
#include <fcntl.h>
#include <io.h>
#include <windows.h>
#include <sstream>

int main()
{
    _setmode(_fileno(stdin), _O_BINARY); 
    _setmode(_fileno(stdout), _O_BINARY );
    char cBuffer[65536] = {0}; 
    while(true) {
        unsigned int length = 0;
        std::cin.read((char*)&length, sizeof(length));
        if (length != 0 && length < 65536) {
            memset(cBuffer, 0, 65536);
            std::cin.read(cBuffer, length);
            std::string strIn(cBuffer);
            std::ostringstream os;
            char computerName[256];
            DWORD computerNameLength = sizeof(computerName);
            GetComputerNameA(computerName, &computerNameLength);
            os << "{ \"data\": \"" << computerName << "\" }";
            std::string output = os.str();
            unsigned int len = output.length();
            std::cout.write((char*)&len, sizeof(len));
            std::cout.write(output.data(), len);
        } else {
            break;
        }
    }
	return 0;
}