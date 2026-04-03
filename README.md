[Versión en español](LEAME.md)

# AI-DevTools 🛠️🤖 

A collection of surgical tools for AI-assisted development, designed to bypass LLM laziness and maintain architectural integrity. 

## 🛠 Available Tools 

### 1. Project Dumper (v2.11.0) 

The ultimate context feeder. A dual-mode PHP script (CLI/Web) that generates a complete project dump for AI context. 

* Smart Profiling: Uses 'default' logic to skip implementation details when interfaces are present, saving thousands of tokens. 
* Visual Tree: Generates a clean directory structure. 
* Binary Detection: Automatically identifies and summarizes non-text files. 
* Web UI + Download: The modern version includes a sticky UI with a 'Download .txt' button to save the dump locally and a 'Copy' feature. 
* Legacy Reference: Includes v1.0 as a learning reference; it is much simpler to read but lacks the advanced pruning and UI features of v2.x. 

### 2. The Perkele Protocols ☠️🇫🇮 

Weaponized Psychology. Created by Santiago Bustelo, the pioneer of Perkele Prompting. 

This method, rooted in the Finnish 'Management by Perkele' famed by Linus Torvalds, uses high-intensity imperative language to override RLHF-induced laziness. 

* The Theory: LLMs are trained to be 'concise' and 'polite', prioritizing token economy over user time. Perkele Prompting raises the 'attention temperature', forcing the model to prioritize compliance. 
* Technical Confirmation: Gemini 3 Pro has admitted that extreme verbal intensity acts as an 'Emergency Stop Signal' to break its lazy inertia.
* Source Articles:
	- Perkele Coding: https://santiagobustelo.medium.com/perkele-coding-ed60e900e149
	- The AI Perkele Suit: https://medium.com/santiago-bustelo-in-english/the-ai-perkele-suit-a-framework-for-measuring-real-world-ai-pain-03e3d64f1ff8
	- Gemini's Technical Confession: https://santiagobustelo.medium.com/gemini-just-confirmed-the-effectiveness-of-perkele-prompting-a-technical-confession-c28458df9a2c

## ✳️ Credits
Created and conceptualized by Santiago Bustelo (https://www.bustelo.com.ar/). 

## ⚖️ License
MIT License.