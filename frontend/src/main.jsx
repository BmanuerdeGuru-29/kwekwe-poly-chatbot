import React from 'react'
import ReactDOM from 'react-dom/client'
import ChatWidget from './components/ChatWidget'
import './styles/globals.css'

// Main app component for standalone development
function App() {
  const [isOpen, setIsOpen] = React.useState(false)

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Demo content */}
      <div className="container mx-auto px-4 py-8">
        <h1 className="text-3xl font-bold text-center mb-8 text-brand-primary">
          Kwekwe Polytechnic Chatbot Demo
        </h1>
        <div className="text-center text-gray-600">
          <p className="mb-4">
            Click the chat button in the bottom-right corner to start a conversation with our AI assistant.
          </p>
          <p>
            Ask about courses, fees, admissions, or any other information about Kwekwe Polytechnic.
          </p>
        </div>
      </div>

      {/* Chat Widget */}
      <ChatWidget 
        isOpen={isOpen}
        onToggle={() => setIsOpen(!isOpen)}
        position="bottom-right"
      />
    </div>
  )
}

// Export for embedding
export { ChatWidget }

// Render for development
if (typeof window !== 'undefined') {
  const root = ReactDOM.createRoot(document.getElementById('root'))
  root.render(<App />)
}
