#include <iostream>
#include <fstream>
#include <unordered_set>
#include <string>
#include <vector>
#include <algorithm>

std::unordered_set<std::string> dictionary;

void loadDictionary(const std::string& filename) {
    std::ifstream file(filename);
    std::string word;
    while (file >> word) {
        dictionary.insert(word);
    }
}

int scoreLetter(char letter) {
    int letterValue[26] = 
    {
        // A-Z letter scores (based on English Scrabble values)
        1, 3, 3, 2, 1, 4, 2, 4, 1, 8, 5, 1, 3, 1, 1, 3, 10, 1, 1, 1, 1, 4, 4, 8, 4, 10
    };
    // Ensure uppercase letters are scored correctly
    letter = toupper(letter);

    // Check if the letter is within the valid range (A-Z)
    if (letter >= 'A' && letter <= 'Z') {
        return letterValue[letter - 'A'];
    } else {
        // Handle invalid letters (usually score 0)
        return 0;
    }
}

int scoreWord(const std::string& word) {
    int totalScore = 0;
    for (char letter : word) {
        totalScore += scoreLetter(letter);
    }
    return totalScore;
}

std::vector<std::string> findValidWords(const std::string& letters, std::string currentWord = "") {
    std::vector<std::string> validWords;

    if (dictionary.count(currentWord)) {
        validWords.push_back(currentWord);
    }

    if (currentWord.length() == letters.length()) {
        return validWords;
    }

    // Explore letters in descending order to prioritize longer words
    for (int i = letters.length() - 1; i >= 0; i--) {
        char letter = letters[i];
        std::string remainingLetters = letters.substr(0, i) + letters.substr(i + 1);

        if (currentWord.find(letter) == std::string::npos) {
            std::vector<std::string> words = findValidWords(remainingLetters, currentWord + letter);
            validWords.insert(validWords.end(), words.begin(), words.end());
        }
    }

    return validWords;
}


std::string findHighestScoringWord(const std::vector<std::string>& words) {
    std::string bestWord;
    for (const std::string& word : words) {
        if (word.length() > bestWord.length() || (word.length() == bestWord.length() && scoreWord(word) > scoreWord(bestWord))) {
            bestWord = word;
        }
    }
    return bestWord;
}


int main() {
    loadDictionary("dictionary.txt");
    std::string letters;
    std::cout << "Enter your letters (no spaces): ";
    std::cin >> letters;

    std::vector<std::string> validWords = findValidWords(letters);
    std::string bestWord = findHighestScoringWord(validWords);

    std::cout << "Highest scoring word: " << bestWord << " (score: " << scoreWord(bestWord) << ")" << std::endl;
    return 0;
}