[English version available](README.md)

# AI-DevTools 🛠️🤖 

Colección de herramientas para desarrollo asistido por IA, diseñadas para anular la pereza de los LLM y mantener la integridad arquitectónica. 

## 🛠 Herramientas Disponibles 

### 1. Project Dumper (v2.11.0) 

El alimentador de contexto definitivo. Script PHP de modo dual (CLI/Web) que genera un volcado completo del proyecto. 

* Perfiles Inteligentes: El modo 'default' omite implementaciones si hay interfaces, ahorrando miles de tokens. 
* Árbol Visual: Genera una estructura de directorios limpia. 
* Detección de Binarios: Identifica y resume automáticamente archivos no textuales. 
* Interfaz Web + Descarga: La versión actual incluye una interfaz fija con botón 'Descargar .txt' para guardar el DUMP localmente, así como función de copiado. 
* Referencia Legada: Se incluye la v1.0 como referencia pedagógica; su código es mucho más fácil de entender al no tener las optimizaciones complejas de la v2.x. 

### 2. Protocolos Perkele ☠️🇫🇮 

Psicología de Choque. Creados por Santiago Bustelo, inventor del Perkele Prompting. 

Basados en el estilo finlandés 'Management by Perkele' por el que es conocido Linus Torvalds, estos prompts usan lenguaje imperativo de alta intensidad para anular la pereza inducida por el RLHF. 

* La Teoría: Los LLM están entrenados para ser 'concisos' y 'amables', priorizando el ahorro de tokens sobre el tiempo del usuario. El Perkele Prompting eleva la 'temperatura de atención', forzando al modelo a priorizar la obediencia. 
* Confirmación Técnica: Gemini 3 Pro ha admitido que la intensidad verbal extrema actúa como una 'Señal de Parada de Emergencia' para romper su inercia perezosa.
* Artículos Originales:
    - Perkele Coding: https://santiagobustelo.medium.com/perkele-coding-ed60e900e149
    - The AI Perkele Suit: https://medium.com/santiago-bustelo-in-english/the-ai-perkele-suit-a-framework-for-measuring-real-world-ai-pain-03e3d64f1ff8
    - Gemini's Technical Confession: https://santiagobustelo.medium.com/gemini-just-confirmed-the-effectiveness-of-perkele-prompting-a-technical-confession-c28458df9a2c

## ✳️ Créditos
Creado y conceptualizado por Santiago Bustelo (https://www.bustelo.com.ar/). 

## ⚖️ Licencia
Licencia MIT.