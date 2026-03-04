import React, { useRef, useEffect } from 'react'
import { ScrollArea } from './ui/scroll-area'
import { Card } from './ui/card'
import { formatTime } from '../lib/utils'
import { Bot, User, Check, CheckCheck } from 'lucide-react'

const MessageList = ({ messages, isLoading }) => {
  const scrollRef = useRef(null)

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight
    }
  }, [messages])

  const MessageBubble = ({ message }) => {
    const isUser = message.role === 'user'
    const isTyping = message.role === 'typing'

    if (isTyping) {
      return (
        <div className="flex justify-start mb-4">
          <div className="flex items-end space-x-2 max-w-[80%]">
            <div className="w-8 h-8 rounded-full bg-brand-primary flex items-center justify-center flex-shrink-0">
              <Bot className="w-4 h-4 text-white" />
            </div>
            <div className="px-4 py-3 rounded-2xl rounded-bl-none bg-muted">
              <div className="flex space-x-1">
                <div className="w-2 h-2 bg-muted-foreground rounded-full animate-pulse"></div>
                <div className="w-2 h-2 bg-muted-foreground rounded-full animate-pulse delay-75"></div>
                <div className="w-2 h-2 bg-muted-foreground rounded-full animate-pulse delay-150"></div>
              </div>
            </div>
          </div>
        </div>
      )
    }

    return (
      <div className={`flex ${isUser ? 'justify-end' : 'justify-start'} mb-4 message-bubble`}>
        <div className={`flex items-end space-x-2 max-w-[80%] ${isUser ? 'flex-row-reverse space-x-reverse' : ''}`}>
          <div className={`w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${
            isUser ? 'bg-brand-secondary' : 'bg-brand-primary'
          }`}>
            {isUser ? (
              <User className="w-4 h-4 text-brand-primary" />
            ) : (
              <Bot className="w-4 h-4 text-white" />
            )}
          </div>
          <div className={`px-4 py-3 rounded-2xl ${
            isUser 
              ? 'bg-brand-primary text-white rounded-br-none' 
              : 'bg-muted rounded-bl-none'
          }`}>
            <p className={`text-sm ${isUser ? 'text-white' : 'text-foreground'}`}>
              {message.content}
            </p>
            {message.timestamp && (
              <div className={`flex items-center justify-between mt-1 space-x-2`}>
                <span className={`text-xs ${isUser ? 'text-brand-secondary/80' : 'text-muted-foreground'}`}>
                  {formatTime(message.timestamp)}
                </span>
                {isUser && (
                  <div className="flex space-x-1">
                    {message.read ? (
                      <CheckCheck className="w-3 h-3 text-brand-secondary" />
                    ) : (
                      <Check className="w-3 h-3 text-brand-secondary/60" />
                    )}
                  </div>
                )}
              </div>
            )}
            {message.sources && message.sources.length > 0 && (
              <div className="mt-2 pt-2 border-t border-border/20">
                <p className={`text-xs ${isUser ? 'text-brand-secondary/80' : 'text-muted-foreground'} mb-1`}>
                  Sources:
                </p>
                {message.sources.slice(0, 2).map((source, index) => (
                  <div key={index} className={`text-xs ${isUser ? 'text-brand-secondary/70' : 'text-muted-foreground'} mb-1`}>
                    • {source.metadata?.filename || 'Institutional Document'}
                    {source.similarity_score && (
                      <span className={`ml-1 ${isUser ? 'text-brand-secondary/60' : 'text-muted-foreground'}`}>
                        ({Math.round(source.similarity_score * 100)}% match)
                      </span>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    )
  }

  return (
    <ScrollArea className="flex-1 p-4" ref={scrollRef}>
      <div className="space-y-4">
        {messages.length === 0 && !isLoading && (
          <div className="text-center py-8">
            <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-brand-primary/10 flex items-center justify-center">
              <Bot className="w-8 h-8 text-brand-primary" />
            </div>
            <h3 className="text-lg font-semibold text-foreground mb-2">
              Welcome to Kwekwe Polytechnic Chatbot
            </h3>
            <p className="text-muted-foreground max-w-md mx-auto">
              Ask me about courses, fees, admissions, or any other information about Kwekwe Polytechnic.
            </p>
            <div className="mt-6 grid grid-cols-1 gap-2 max-w-sm mx-auto">
              {[
                "What are the entry requirements for Engineering?",
                "How much are the tuition fees?",
                "What payment methods are accepted?",
                "Who heads the Automotive Engineering department?"
              ].map((suggestion, index) => (
                <Card 
                  key={index} 
                  className="p-3 cursor-pointer hover:bg-muted/50 transition-colors text-sm text-muted-foreground"
                  onClick={() => {
                    // This would trigger the suggestion to be sent
                    const event = new CustomEvent('chatSuggestion', { detail: suggestion })
                    window.dispatchEvent(event)
                  }}
                >
                  {suggestion}
                </Card>
              ))}
            </div>
          </div>
        )}
        
        {messages.map((message, index) => (
          <MessageBubble key={index} message={message} />
        ))}
        
        {isLoading && (
          <MessageBubble 
            message={{
              role: 'typing',
              content: '',
              timestamp: new Date().toISOString()
            }} 
          />
        )}
      </div>
    </ScrollArea>
  )
}

export default MessageList
