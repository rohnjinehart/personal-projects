#include <iostream>
#include <fstream>
#include <string>

int main() {
   std::ifstream inputFile("words.txt");

   if (!inputFile.is_open()) {
       std::cerr << "Error opening file!\n";
       return 1;  // Indicate error
   }

   std::string wordToFind;
   std::cout << "Enter the word to search for: ";
   std::cin >> wordToFind;

   std::string line;
   int lineNumber = 1;
   bool found = false;

   while (getline(inputFile, line)) {
       size_t position = line.find(wordToFind);

       while (position != std::string::npos) {
           std::cout << "Word found on line " << lineNumber << " at position " << position << std::endl;
           found = true;

           // Continue searching for the word on the same line
           position = line.find(wordToFind, position + wordToFind.length());
       }

       lineNumber++;
   }

   if (!found) {
       std::cout << "Word not found in the file.\n";
   }

   inputFile.close();

   return 0;
}